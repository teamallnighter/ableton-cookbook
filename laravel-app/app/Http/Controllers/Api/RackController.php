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

class RackController extends Controller
{
    protected RackProcessingService $rackService;

    public function __construct(RackProcessingService $rackService)
    {
        $this->rackService = $rackService;
    }

    /**
     * Display a listing of racks with advanced filtering
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
     * Store a newly created rack
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
     * Display the specified rack
     */
    public function show(Rack $rack): JsonResponse
    {
        if (!$rack->is_public && $rack->user_id !== auth()->id()) {
            abort(403, 'This rack is private.');
        }

        $rack->increment('views_count');

        $rack->load([
            'user:id,name,profile_photo_path',
            'tags',
            'ratings' => function ($query) {
                $query->with('user:id,name,profile_photo_path')
                    ->latest()
                    ->limit(10);
            },
        ]);

        return response()->json($rack);
    }

    /**
     * Update the specified rack
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
            $this->rackService->attachTags($rack, $request->tags);
        }

        return response()->json([
            'message' => 'Rack updated successfully!',
            'rack' => $rack->fresh(['user:id,name,profile_photo_path', 'tags']),
        ]);
    }

    /**
     * Remove the specified rack
     */
    public function destroy(Rack $rack): JsonResponse
    {
        Gate::authorize('delete', $rack);

        $rack->delete();

        return response()->json([
            'message' => 'Rack deleted successfully!',
        ]);
    }

    /**
     * Download a rack
     */
    public function download(Rack $rack): JsonResponse
    {
        if (!$rack->is_public && $rack->user_id !== auth()->id()) {
            abort(403, 'This rack is private.');
        }

        $rack->recordDownload(auth()->user());

        return response()->json([
            'download_url' => $rack->getDownloadUrl(),
        ]);
    }

    /**
     * Like/unlike a rack
     */
    public function toggleLike(Rack $rack): JsonResponse
    {
        $user = auth()->user();
        
        if ($user->hasLiked($rack)) {
            $user->unlike($rack);
            $message = 'Rack unliked!';
        } else {
            $user->like($rack);
            $message = 'Rack liked!';
        }

        $rack->increment('likes_count');

        return response()->json([
            'message' => $message,
            'likes_count' => $rack->likes_count,
        ]);
    }

    /**
     * Get trending racks
     */
    public function trending(): JsonResponse
    {
        $racks = Rack::published()
            ->with(['user:id,name,profile_photo_path', 'tags'])
            ->where('created_at', '>=', now()->subDays(7))
            ->orderByDesc('downloads_count')
            ->orderByDesc('views_count')
            ->limit(20)
            ->get();

        return response()->json($racks);
    }

    /**
     * Get featured racks
     */
    public function featured(): JsonResponse
    {
        $racks = Rack::published()
            ->featured()
            ->with(['user:id,name,profile_photo_path', 'tags'])
            ->orderByDesc('created_at')
            ->limit(12)
            ->get();

        return response()->json($racks);
    }
}