<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rack;
use App\Services\RackProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @OA\Tag(
 *     name="Racks",
 *     description="API Endpoints for managing Ableton Live racks"
 * )
 */
class RackController extends Controller
{
    protected RackProcessingService $rackService;

    public function __construct(RackProcessingService $rackService)
    {
        $this->rackService = $rackService;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/racks",
     *     summary="Get a list of racks",
     *     description="Retrieve a paginated list of published racks with optional filtering and sorting",
     *     operationId="getRacks",
     *     tags={"Racks"},
     *     @OA\Parameter(
     *         name="filter[rack_type]",
     *         in="query",
     *         description="Filter by rack type (instrument, audio_effect, midi_effect)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"instrument", "audio_effect", "midi_effect"})
     *     ),
     *     @OA\Parameter(
     *         name="filter[user_id]",
     *         in="query",
     *         description="Filter by user ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="filter[tags]",
     *         in="query",
     *         description="Filter by tags (comma-separated)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="filter[rating]",
     *         in="query",
     *         description="Filter by minimum rating",
     *         required=false,
     *         @OA\Schema(type="number", format="float", minimum=0, maximum=5)
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Sort by field (prefix with - for descending)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"-created_at", "created_at", "-downloads_count", "downloads_count", "-average_rating", "average_rating", "-views_count", "views_count"})
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(type="object")
     *             ),
     *             @OA\Property(property="current_page", type="integer"),
     *             @OA\Property(property="per_page", type="integer"),
     *             @OA\Property(property="total", type="integer")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $racks = QueryBuilder::for(Rack::class)
            ->published()
            ->with(['user:id,name,profile_photo_path', 'tags'])
            ->allowedFilters([
                AllowedFilter::exact('rack_type'),
                AllowedFilter::exact('user_id'),
                AllowedFilter::scope('featured'),
                AllowedFilter::callback('devices', function ($query, $value) {
                    $query->whereJsonContains('devices', $value);
                }),
                AllowedFilter::callback('tags', function ($query, $value) {
                    $query->whereHas('tags', function ($q) use ($value) {
                        $q->whereIn('slug', explode(',', $value));
                    });
                }),
                AllowedFilter::callback('rating', function ($query, $value) {
                    $query->where('average_rating', '>=', $value);
                }),
            ])
            ->allowedSorts(['created_at', 'downloads_count', 'average_rating', 'views_count'])
            ->defaultSort('-created_at')
            ->paginate($request->get('per_page', 20));

        return response()->json($racks);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/racks",
     *     summary="Upload a new rack",
     *     description="Create a new rack by uploading an Ableton device group (.adg) file",
     *     operationId="createRack",
     *     tags={"Racks"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="file",
     *                     type="string",
     *                     format="binary",
     *                     description="Ableton device group (.adg) file"
     *                 ),
     *                 @OA\Property(
     *                     property="title",
     *                     type="string",
     *                     maxLength=255,
     *                     description="Rack title"
     *                 ),
     *                 @OA\Property(
     *                     property="description",
     *                     type="string",
     *                     maxLength=1000,
     *                     description="Rack description"
     *                 ),
     *                 @OA\Property(
     *                     property="tags",
     *                     type="array",
     *                     maxItems=10,
     *                     @OA\Items(type="string", maxLength=50),
     *                     description="Tags for the rack"
     *                 ),
     *                 @OA\Property(
     *                     property="is_public",
     *                     type="boolean",
     *                     description="Whether the rack is public"
     *                 ),
     *                 required={"file", "title"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Rack created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="rack", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Duplicate rack file",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="rack", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or processing failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:50000|mimes:adg',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'tags' => 'nullable|array|max:10',
            'tags.*' => 'string|max:50',
            'is_public' => 'boolean',
        ]);

        // Check for duplicate
        $fileHash = hash_file('sha256', $request->file('file')->path());
        if ($duplicate = $this->rackService->isDuplicate($fileHash)) {
            return response()->json([
                'message' => 'This rack file has already been uploaded.',
                'rack' => $duplicate,
            ], 409);
        }

        try {
            $rack = $this->rackService->processRack(
                $request->file('file'),
                $request->user(),
                $request->only(['title', 'description', 'tags', 'is_public'])
            );

            $rack->load(['user:id,name,profile_photo_path', 'tags']);

            return response()->json([
                'message' => 'Rack uploaded successfully!',
                'rack' => $rack,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to process rack file.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/racks/{id}",
     *     summary="Get a specific rack",
     *     description="Retrieve details of a specific rack by ID",
     *     operationId="getRack",
     *     tags={"Racks"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Rack ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Rack not found"
     *     )
     * )
     */
    public function show(Rack $rack): JsonResponse
    {
        $rack->load(['user:id,name,profile_photo_path,created_at', 'tags', 'comments.user:id,name,profile_photo_path']);
        $rack->increment('views_count');
        
        return response()->json($rack);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/racks/trending",
     *     summary="Get trending racks",
     *     description="Retrieve racks that are currently trending",
     *     operationId="getTrendingRacks",
     *     tags={"Racks"},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of results to return",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=50, default=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(type="object")
     *         )
     *     )
     * )
     */
    public function trending(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 20);
        
        $racks = Rack::published()
            ->with(['user:id,name,profile_photo_path', 'tags'])
            ->where('created_at', '>=', now()->subWeeks(2))
            ->orderByDesc('downloads_count')
            ->orderByDesc('average_rating')
            ->limit($limit)
            ->get();

        return response()->json($racks);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/racks/featured",
     *     summary="Get featured racks",
     *     description="Retrieve racks that are featured by administrators",
     *     operationId="getFeaturedRacks",
     *     tags={"Racks"},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of results to return",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=50, default=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(type="object")
     *         )
     *     )
     * )
     */
    public function featured(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 20);
        
        $racks = Rack::featured()
            ->published()
            ->with(['user:id,name,profile_photo_path', 'tags'])
            ->orderByDesc('featured_at')
            ->limit($limit)
            ->get();

        return response()->json($racks);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/racks/{id}",
     *     summary="Update a rack",
     *     description="Update rack details (owner only)",
     *     operationId="updateRack",
     *     tags={"Racks"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Rack ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", maxLength=255),
     *             @OA\Property(property="description", type="string", maxLength=1000),
     *             @OA\Property(property="is_public", type="boolean"),
     *             @OA\Property(
     *                 property="tags",
     *                 type="array",
     *                 maxItems=10,
     *                 @OA\Items(type="string", maxLength=50)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Rack updated successfully",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - not the owner"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Rack not found"
     *     )
     * )
     */
    public function update(Request $request, Rack $rack): JsonResponse
    {
        Gate::authorize('update', $rack);

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'tags' => 'nullable|array|max:10',
            'tags.*' => 'string|max:50',
            'is_public' => 'boolean',
        ]);

        $rack->update($request->only(['title', 'description', 'is_public']));
        
        if ($request->has('tags')) {
            $this->rackService->syncTags($rack, $request->tags);
        }

        $rack->load(['user:id,name,profile_photo_path', 'tags']);

        return response()->json($rack);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/racks/{id}",
     *     summary="Delete a rack",
     *     description="Delete a rack (owner only)",
     *     operationId="deleteRack",
     *     tags={"Racks"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Rack ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Rack deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - not the owner"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Rack not found"
     *     )
     * )
     */
    public function destroy(Rack $rack): JsonResponse
    {
        Gate::authorize('delete', $rack);
        
        $this->rackService->deleteRack($rack);
        
        return response()->json(null, 204);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/racks/{id}/like",
     *     summary="Toggle like on a rack",
     *     description="Like or unlike a rack",
     *     operationId="toggleLikeRack",
     *     tags={"Racks"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Rack ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Like toggled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="liked", type="boolean"),
     *             @OA\Property(property="likes_count", type="integer")
     *         )
     *     )
     * )
     */
    public function toggleLike(Request $request, Rack $rack): JsonResponse
    {
        $user = $request->user();
        $liked = $user->toggleLike($rack);
        
        return response()->json([
            'liked' => $liked,
            'likes_count' => $rack->fresh()->likesCount,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/racks/{id}/download",
     *     summary="Download a rack",
     *     description="Track a rack download and return download URL",
     *     operationId="downloadRack",
     *     tags={"Racks"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Rack ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Download initiated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="download_url", type="string"),
     *             @OA\Property(property="filename", type="string")
     *         )
     *     )
     * )
     */
    public function download(Request $request, Rack $rack): JsonResponse
    {
        // Track the download
        $rack->increment('downloads_count');
        $rack->downloads()->create([
            'user_id' => $request->user()->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'download_url' => $rack->download_url,
            'filename' => $rack->original_filename,
        ]);
    }
}
