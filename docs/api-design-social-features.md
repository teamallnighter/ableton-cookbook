# API Design for Social Features - Ableton Cookbook

## API Architecture Overview

The Ableton Cookbook API follows RESTful principles with modern Laravel best practices for social media platforms:

- **Versioned APIs** (v1, v2) for backward compatibility
- **Resource-based endpoints** with consistent naming conventions
- **Comprehensive filtering and pagination**
- **Real-time capabilities** through WebSockets and broadcasting
- **Rate limiting** for different feature categories
- **Standardized response formats** with proper error handling

## Core API Endpoints

### User Management API

```php
<?php
// routes/api.php - User Management

Route::prefix('v1')->group(function () {
    // Public user endpoints
    Route::get('users', [UserController::class, 'index']);
    Route::get('users/{user:username}', [UserController::class, 'show']);
    Route::get('users/{user:username}/workflows', [UserController::class, 'workflows']);
    Route::get('users/{user:username}/followers', [UserController::class, 'followers']);
    Route::get('users/{user:username}/following', [UserController::class, 'following']);
    
    // Authenticated user endpoints
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('profile', [UserController::class, 'profile']);
        Route::put('profile', [UserController::class, 'updateProfile']);
        Route::post('profile/avatar', [UserAvatarController::class, 'store']);
        Route::delete('profile/avatar', [UserAvatarController::class, 'destroy']);
    });
});
```

### Workflow API with Advanced Features

```php
<?php
// app/Http/Controllers/Api/V1/WorkflowController.php

use App\Http\Resources\WorkflowResource;
use App\Http\Resources\WorkflowCollection;
use App\Services\WorkflowSearchService;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class WorkflowController extends Controller
{
    public function __construct(
        protected WorkflowSearchService $searchService
    ) {}

    /**
     * GET /api/v1/workflows
     * Advanced filtering and search for workflows
     */
    public function index(Request $request)
    {
        $workflows = QueryBuilder::for(Workflow::class)
            ->allowedFilters([
                AllowedFilter::exact('rack_type'),
                AllowedFilter::exact('genre'),
                AllowedFilter::exact('difficulty_level'),
                AllowedFilter::scope('by_user'),
                AllowedFilter::scope('by_tag'),
                AllowedFilter::callback('bpm_range', function ($query, $value) {
                    $range = explode(',', $value);
                    if (count($range) === 2) {
                        return $query->whereBetween('bpm', [(int)$range[0], (int)$range[1]]);
                    }
                }),
                AllowedFilter::callback('rating_min', function ($query, $value) {
                    return $query->where('average_rating', '>=', (float)$value);
                }),
                AllowedFilter::callback('created_after', function ($query, $value) {
                    return $query->where('created_at', '>=', $value);
                }),
                AllowedFilter::callback('search', function ($query, $value) {
                    return $this->searchService->searchWorkflows($query, $value);
                }),
            ])
            ->allowedSorts([
                'created_at',
                'average_rating',
                'downloads_count',
                'likes_count',
                'title',
                AllowedSort::field('popularity', 'downloads_count'),
                AllowedSort::field('trending', 'likes_count'), // Could be more complex trending algorithm
            ])
            ->allowedIncludes([
                'user',
                'tags',
                'ratings',
                'comments.user',
                'likes.user'
            ])
            ->where('is_published', true)
            ->with(['user:id,username,display_name,is_verified'])
            ->withCount(['likes', 'comments', 'downloads'])
            ->paginate($request->input('per_page', 20))
            ->withQueryString();

        return new WorkflowCollection($workflows);
    }

    /**
     * GET /api/v1/workflows/{workflow:slug}
     * Get single workflow with full details
     */
    public function show(Request $request, Workflow $workflow)
    {
        $this->authorize('view', $workflow);

        // Track view
        $this->trackWorkflowView($workflow, $request);

        $workflow->load([
            'user:id,username,display_name,bio,is_verified',
            'tags',
            'ratings.user:id,username,display_name',
            'comments.user:id,username,display_name',
            'comments.replies.user:id,username,display_name'
        ]);

        $workflow->loadCount(['likes', 'comments', 'downloads']);

        // Add user interaction data if authenticated
        if (auth()->check()) {
            $workflow->is_liked_by_user = $workflow->isLikedByUser(auth()->user());
            $workflow->user_rating = $workflow->getUserRating(auth()->user());
        }

        return new WorkflowResource($workflow);
    }

    /**
     * POST /api/v1/workflows
     * Create new workflow
     */
    public function store(WorkflowUploadRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $workflow = $this->workflowService->createWorkflow($request->validated(), auth()->user());
            
            // Broadcast new workflow event
            broadcast(new WorkflowCreated($workflow))->toOthers();
            
            return new WorkflowResource($workflow);
        });
    }

    /**
     * PUT /api/v1/workflows/{workflow:slug}
     * Update workflow
     */
    public function update(WorkflowUpdateRequest $request, Workflow $workflow)
    {
        $this->authorize('update', $workflow);

        $workflow = $this->workflowService->updateWorkflow($workflow, $request->validated());

        return new WorkflowResource($workflow);
    }

    /**
     * DELETE /api/v1/workflows/{workflow:slug}
     */
    public function destroy(Workflow $workflow)
    {
        $this->authorize('delete', $workflow);

        $this->workflowService->deleteWorkflow($workflow);

        return response()->json(['message' => 'Workflow deleted successfully']);
    }

    /**
     * POST /api/v1/workflows/{workflow:slug}/download
     * Generate download link
     */
    public function download(Workflow $workflow)
    {
        $this->authorize('download', $workflow);

        return $this->workflowService->generateDownloadLink($workflow, auth()->user());
    }

    protected function trackWorkflowView(Workflow $workflow, Request $request): void
    {
        // Prevent duplicate views within same session
        $sessionKey = "workflow_view_{$workflow->id}";
        
        if (!session()->has($sessionKey)) {
            WorkflowView::create([
                'workflow_id' => $workflow->id,
                'user_id' => auth()->id(),
                'ip_address' => $request->ip(),
                'session_id' => session()->getId(),
            ]);

            session()->put($sessionKey, true);
        }
    }
}
```

### Social Interaction APIs

```php
<?php
// app/Http/Controllers/Api/V1/WorkflowInteractionController.php

class WorkflowInteractionController extends Controller
{
    /**
     * POST /api/v1/workflows/{workflow:slug}/like
     */
    public function like(Workflow $workflow)
    {
        $user = auth()->user();
        
        if ($workflow->isLikedByUser($user)) {
            return response()->json(['message' => 'Already liked'], 409);
        }

        DB::transaction(function () use ($workflow, $user) {
            WorkflowLike::create([
                'workflow_id' => $workflow->id,
                'user_id' => $user->id,
            ]);

            $workflow->increment('likes_count');

            // Create activity log
            activity('workflow_like')
                ->performedOn($workflow)
                ->by($user)
                ->log('liked workflow');

            // Notify workflow owner
            if ($workflow->user_id !== $user->id) {
                $workflow->user->notify(new WorkflowLiked($workflow, $user));
            }

            // Broadcast real-time update
            broadcast(new WorkflowLikeToggled($workflow, $user, 'liked'));
        });

        return response()->json(['message' => 'Workflow liked', 'likes_count' => $workflow->fresh()->likes_count]);
    }

    /**
     * DELETE /api/v1/workflows/{workflow:slug}/like
     */
    public function unlike(Workflow $workflow)
    {
        $user = auth()->user();
        
        $like = WorkflowLike::where('workflow_id', $workflow->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$like) {
            return response()->json(['message' => 'Not liked'], 409);
        }

        DB::transaction(function () use ($workflow, $user, $like) {
            $like->delete();
            $workflow->decrement('likes_count');

            // Broadcast real-time update
            broadcast(new WorkflowLikeToggled($workflow, $user, 'unliked'));
        });

        return response()->json(['message' => 'Workflow unliked', 'likes_count' => $workflow->fresh()->likes_count]);
    }

    /**
     * POST /api/v1/workflows/{workflow:slug}/rating
     */
    public function rate(WorkflowRatingRequest $request, Workflow $workflow)
    {
        $user = auth()->user();

        if ($workflow->user_id === $user->id) {
            return response()->json(['message' => 'Cannot rate your own workflow'], 403);
        }

        DB::transaction(function () use ($request, $workflow, $user) {
            WorkflowRating::updateOrCreate(
                ['workflow_id' => $workflow->id, 'user_id' => $user->id],
                [
                    'rating' => $request->rating,
                    'review' => $request->review,
                    'is_recommended' => $request->boolean('is_recommended'),
                ]
            );

            // Recalculate average rating
            $this->recalculateWorkflowRating($workflow);

            // Log activity
            activity('workflow_rating')
                ->performedOn($workflow)
                ->by($user)
                ->withProperties(['rating' => $request->rating])
                ->log('rated workflow');
        });

        return response()->json([
            'message' => 'Rating submitted',
            'average_rating' => $workflow->fresh()->average_rating,
            'ratings_count' => $workflow->fresh()->ratings_count
        ]);
    }

    protected function recalculateWorkflowRating(Workflow $workflow): void
    {
        $ratings = WorkflowRating::where('workflow_id', $workflow->id)->get();
        
        $workflow->update([
            'average_rating' => $ratings->avg('rating'),
            'ratings_count' => $ratings->count(),
        ]);
    }
}
```

### User Following API

```php
<?php
// app/Http/Controllers/Api/V1/FollowController.php

class FollowController extends Controller
{
    /**
     * POST /api/v1/users/{user:username}/follow
     */
    public function follow(User $user)
    {
        $currentUser = auth()->user();

        if ($currentUser->id === $user->id) {
            return response()->json(['message' => 'Cannot follow yourself'], 422);
        }

        if ($currentUser->isFollowing($user)) {
            return response()->json(['message' => 'Already following'], 409);
        }

        DB::transaction(function () use ($currentUser, $user) {
            $currentUser->follow($user);

            // Update counters
            $currentUser->increment('following_count');
            $user->increment('followers_count');

            // Log activity
            activity('user_follow')
                ->performedOn($user)
                ->by($currentUser)
                ->log('started following');

            // Notify the followed user
            $user->notify(new NewFollower($currentUser));

            // Broadcast real-time notification
            broadcast(new UserFollowed($user, $currentUser));
        });

        return response()->json([
            'message' => 'Successfully followed',
            'followers_count' => $user->fresh()->followers_count
        ]);
    }

    /**
     * DELETE /api/v1/users/{user:username}/follow
     */
    public function unfollow(User $user)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isFollowing($user)) {
            return response()->json(['message' => 'Not following'], 409);
        }

        DB::transaction(function () use ($currentUser, $user) {
            $currentUser->unfollow($user);

            // Update counters
            $currentUser->decrement('following_count');
            $user->decrement('followers_count');
        });

        return response()->json([
            'message' => 'Successfully unfollowed',
            'followers_count' => $user->fresh()->followers_count
        ]);
    }

    /**
     * GET /api/v1/users/{user:username}/followers
     */
    public function followers(User $user)
    {
        $followers = $user->followers()
            ->withPivot('created_at')
            ->orderByPivot('created_at', 'desc')
            ->paginate(20);

        return UserCollection::make($followers);
    }

    /**
     * GET /api/v1/users/{user:username}/following
     */
    public function following(User $user)
    {
        $following = $user->followings()
            ->withPivot('created_at')
            ->orderByPivot('created_at', 'desc')
            ->paginate(20);

        return UserCollection::make($following);
    }
}
```

### Comments API with Nested Threading

```php
<?php
// app/Http/Controllers/Api/V1/WorkflowCommentController.php

class WorkflowCommentController extends Controller
{
    /**
     * GET /api/v1/workflows/{workflow:slug}/comments
     */
    public function index(Workflow $workflow)
    {
        $comments = WorkflowComment::where('workflow_id', $workflow->id)
            ->whereNull('parent_id') // Top-level comments only
            ->with([
                'user:id,username,display_name,is_verified',
                'replies.user:id,username,display_name,is_verified',
                'replies.likes'
            ])
            ->withCount(['likes', 'replies'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Add user interaction data
        if (auth()->check()) {
            $comments->getCollection()->transform(function ($comment) {
                $comment->is_liked_by_user = $comment->isLikedByUser(auth()->user());
                $comment->replies->transform(function ($reply) {
                    $reply->is_liked_by_user = $reply->isLikedByUser(auth()->user());
                    return $reply;
                });
                return $comment;
            });
        }

        return WorkflowCommentCollection::make($comments);
    }

    /**
     * POST /api/v1/workflows/{workflow:slug}/comments
     */
    public function store(WorkflowCommentRequest $request, Workflow $workflow)
    {
        $comment = DB::transaction(function () use ($request, $workflow) {
            $comment = WorkflowComment::create([
                'workflow_id' => $workflow->id,
                'user_id' => auth()->id(),
                'parent_id' => $request->parent_id,
                'content' => $request->content,
            ]);

            $workflow->increment('comments_count');

            // Log activity
            activity('workflow_comment')
                ->performedOn($workflow)
                ->by(auth()->user())
                ->log('commented on workflow');

            // Notify workflow owner (if not commenting on own workflow)
            if ($workflow->user_id !== auth()->id()) {
                $workflow->user->notify(new WorkflowCommented($workflow, $comment));
            }

            // Notify parent comment author for replies
            if ($comment->parent_id) {
                $parentComment = WorkflowComment::find($comment->parent_id);
                if ($parentComment->user_id !== auth()->id()) {
                    $parentComment->user->notify(new CommentReplied($parentComment, $comment));
                }
            }

            return $comment;
        });

        $comment->load('user:id,username,display_name,is_verified');

        // Broadcast real-time comment
        broadcast(new WorkflowCommentCreated($workflow, $comment));

        return new WorkflowCommentResource($comment);
    }

    /**
     * PUT /api/v1/comments/{comment}
     */
    public function update(WorkflowCommentRequest $request, WorkflowComment $comment)
    {
        $this->authorize('update', $comment);

        $comment->update(['content' => $request->content]);

        return new WorkflowCommentResource($comment);
    }

    /**
     * DELETE /api/v1/comments/{comment}
     */
    public function destroy(WorkflowComment $comment)
    {
        $this->authorize('delete', $comment);

        DB::transaction(function () use ($comment) {
            // Soft delete to preserve threading structure
            $comment->delete();

            // Decrement workflow comments count
            $comment->workflow->decrement('comments_count');
        });

        return response()->json(['message' => 'Comment deleted']);
    }

    /**
     * POST /api/v1/comments/{comment}/like
     */
    public function likeComment(WorkflowComment $comment)
    {
        $user = auth()->user();

        if ($comment->isLikedByUser($user)) {
            return response()->json(['message' => 'Already liked'], 409);
        }

        DB::transaction(function () use ($comment, $user) {
            CommentLike::create([
                'comment_id' => $comment->id,
                'user_id' => $user->id,
            ]);

            $comment->increment('likes_count');

            // Notify comment author
            if ($comment->user_id !== $user->id) {
                $comment->user->notify(new CommentLiked($comment, $user));
            }
        });

        return response()->json(['message' => 'Comment liked', 'likes_count' => $comment->fresh()->likes_count]);
    }
}
```

### Activity Feed API

```php
<?php
// app/Http/Controllers/Api/V1/ActivityFeedController.php

class ActivityFeedController extends Controller
{
    /**
     * GET /api/v1/feed
     * Personalized activity feed for authenticated user
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Get activities from followed users + own activities
        $activities = $this->buildPersonalizedFeed($user, $request);

        return ActivityCollection::make($activities);
    }

    /**
     * GET /api/v1/feed/discover
     * Discovery feed with trending content
     */
    public function discover(Request $request)
    {
        $activities = $this->buildDiscoveryFeed($request);

        return ActivityCollection::make($activities);
    }

    /**
     * GET /api/v1/users/{user:username}/activities
     * User's public activity stream
     */
    public function userActivities(User $user, Request $request)
    {
        $activities = UserActivity::where('user_id', $user->id)
            ->where('is_public', true)
            ->with(['user', 'activityable'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return ActivityCollection::make($activities);
    }

    protected function buildPersonalizedFeed(User $user, Request $request): LengthAwarePaginator
    {
        // Use cached feed for performance
        return Cache::remember(
            "user_feed_{$user->id}_{$request->input('page', 1)}",
            now()->addMinutes(10),
            function () use ($user) {
                $followingIds = $user->followings()->pluck('id')->push($user->id);

                return UserActivity::whereIn('user_id', $followingIds)
                    ->where('is_public', true)
                    ->with(['user', 'activityable'])
                    ->orderBy('created_at', 'desc')
                    ->paginate(20);
            }
        );
    }

    protected function buildDiscoveryFeed(Request $request): LengthAwarePaginator
    {
        // Trending algorithm: recent activities with high engagement
        return UserActivity::select('user_activities.*')
            ->join('workflows', function ($join) {
                $join->on('user_activities.activityable_id', '=', 'workflows.id')
                     ->where('user_activities.activityable_type', '=', Workflow::class)
                     ->where('user_activities.activity_type', '=', 'workflow_upload');
            })
            ->where('user_activities.created_at', '>=', now()->subDays(7))
            ->where('user_activities.is_public', true)
            ->orderByRaw('(workflows.likes_count + workflows.downloads_count * 2 + workflows.comments_count * 3) DESC')
            ->with(['user', 'activityable'])
            ->paginate(20);
    }
}
```

## Real-time Features with Broadcasting

### WebSocket Events

```php
<?php
// app/Events/WorkflowLikeToggled.php

class WorkflowLikeToggled implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Workflow $workflow,
        public User $user,
        public string $action // 'liked' or 'unliked'
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("workflow.{$this->workflow->id}");
    }

    public function broadcastWith(): array
    {
        return [
            'workflow_id' => $this->workflow->id,
            'user_id' => $this->user->id,
            'username' => $this->user->username,
            'action' => $this->action,
            'likes_count' => $this->workflow->fresh()->likes_count,
        ];
    }

    public function broadcastAs(): string
    {
        return 'like.toggled';
    }
}

// app/Events/WorkflowCommentCreated.php
class WorkflowCommentCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Workflow $workflow,
        public WorkflowComment $comment
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("workflow.{$this->workflow->id}");
    }

    public function broadcastWith(): array
    {
        return [
            'workflow_id' => $this->workflow->id,
            'comment' => new WorkflowCommentResource($this->comment->load('user')),
            'comments_count' => $this->workflow->fresh()->comments_count,
        ];
    }

    public function broadcastAs(): string
    {
        return 'comment.created';
    }
}
```

## API Response Standards

### Standardized Response Format

```php
<?php
// app/Http/Resources/WorkflowResource.php

class WorkflowResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'slug' => $this->slug,
            'rack_type' => $this->rack_type,
            'genre' => $this->genre,
            'bpm' => $this->bpm,
            'key_signature' => $this->key_signature,
            'difficulty_level' => $this->difficulty_level,
            'preview_audio_url' => $this->preview_audio_url,
            'preview_image_url' => $this->preview_image_url,
            
            // Engagement metrics
            'downloads_count' => $this->downloads_count,
            'likes_count' => $this->likes_count,
            'comments_count' => $this->comments_count,
            'average_rating' => round($this->average_rating, 1),
            'ratings_count' => $this->ratings_count,
            
            // Metadata
            'file_size' => $this->file_size,
            'ableton_version' => $this->ableton_version,
            'devices_used' => $this->devices_used,
            'is_featured' => $this->is_featured,
            'published_at' => $this->published_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // User interaction data (when authenticated)
            'is_liked_by_user' => $this->when(
                auth()->check(),
                fn() => $this->is_liked_by_user ?? false
            ),
            'user_rating' => $this->when(
                auth()->check(),
                fn() => $this->user_rating
            ),
            
            // Relationships
            'user' => new UserResource($this->whenLoaded('user')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'comments' => WorkflowCommentResource::collection($this->whenLoaded('comments')),
            'ratings' => WorkflowRatingResource::collection($this->whenLoaded('ratings')),
        ];
    }
}
```

### Error Handling and Validation

```php
<?php
// app/Exceptions/Handler.php

public function render($request, Throwable $exception)
{
    if ($request->expectsJson()) {
        return $this->renderJsonError($request, $exception);
    }

    return parent::render($request, $exception);
}

protected function renderJsonError($request, Throwable $exception): JsonResponse
{
    $status = 500;
    $message = 'Internal Server Error';
    $errors = null;

    if ($exception instanceof ValidationException) {
        $status = 422;
        $message = 'Validation Error';
        $errors = $exception->errors();
    } elseif ($exception instanceof AuthenticationException) {
        $status = 401;
        $message = 'Unauthenticated';
    } elseif ($exception instanceof AuthorizationException) {
        $status = 403;
        $message = 'Forbidden';
    } elseif ($exception instanceof ModelNotFoundException) {
        $status = 404;
        $message = 'Resource not found';
    } elseif ($exception instanceof ThrottleException) {
        $status = 429;
        $message = 'Too Many Requests';
    }

    $response = [
        'message' => $message,
        'status' => $status,
    ];

    if ($errors) {
        $response['errors'] = $errors;
    }

    if (config('app.debug')) {
        $response['debug'] = [
            'exception' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ];
    }

    return response()->json($response, $status);
}
```

This API design provides:
- RESTful endpoints with consistent patterns
- Advanced filtering and search capabilities
- Real-time features through broadcasting
- Comprehensive social interaction APIs
- Standardized response formats
- Proper error handling and validation
- Performance optimizations with caching
- Security through rate limiting and authorization

The design is scalable and follows Laravel best practices while providing a rich social media experience for Ableton workflow sharing.