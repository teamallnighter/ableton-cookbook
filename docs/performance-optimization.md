# Performance Optimization Strategy for Ableton Cookbook

## Database Optimization

### Query Optimization and Indexing Strategy

```php
<?php
// database/migrations/add_performance_indexes.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPerformanceIndexes extends Migration
{
    public function up()
    {
        Schema::table('workflows', function (Blueprint $table) {
            // Composite indexes for common query patterns
            $table->index(['is_published', 'created_at'], 'idx_published_recent');
            $table->index(['rack_type', 'average_rating'], 'idx_type_rating');
            $table->index(['genre', 'downloads_count'], 'idx_genre_popular');
            $table->index(['user_id', 'is_published', 'created_at'], 'idx_user_published');
            $table->index(['is_featured', 'created_at'], 'idx_featured_recent');
            
            // Full-text search optimization
            $table->fullText(['title', 'description'], 'idx_search_content');
        });

        Schema::table('user_activities', function (Blueprint $table) {
            // Activity feed optimization
            $table->index(['user_id', 'is_public', 'created_at'], 'idx_user_public_feed');
            $table->index(['activity_type', 'created_at'], 'idx_type_timeline');
            
            // Partitioning preparation (monthly partitions)
            $table->index(['created_at'], 'idx_partition_date');
        });

        Schema::table('workflow_views', function (Blueprint $table) {
            // Analytics optimization
            $table->index(['workflow_id', 'created_at'], 'idx_workflow_views');
            $table->index(['user_id', 'created_at'], 'idx_user_views');
            $table->index(['created_at'], 'idx_views_date');
        });

        Schema::table('user_follows', function (Blueprint $table) {
            // Social graph optimization
            $table->index(['follower_id', 'created_at'], 'idx_follower_timeline');
            $table->index(['following_id', 'created_at'], 'idx_following_timeline');
        });
    }
}
```

### Database Connection and Query Optimization

```php
<?php
// config/database.php - Optimized MySQL configuration

'mysql' => [
    'driver' => 'mysql',
    'url' => env('DATABASE_URL'),
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => env('DB_DATABASE', 'forge'),
    'username' => env('DB_USERNAME', 'forge'),
    'password' => env('DB_PASSWORD', ''),
    'unix_socket' => env('DB_SOCKET', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'prefix_indexes' => true,
    'strict' => true,
    'engine' => 'InnoDB',
    'options' => [
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'"
    ],
    'pool' => [
        'min_connections' => env('DB_POOL_MIN', 2),
        'max_connections' => env('DB_POOL_MAX', 20),
        'connect_timeout' => 10,
        'wait_timeout' => 3,
        'heartbeat' => 60,
    ],
],

// Read replicas for scaling
'mysql_read' => [
    'driver' => 'mysql',
    'read' => [
        'host' => [
            env('DB_READ_HOST_1', '127.0.0.1'),
            env('DB_READ_HOST_2', '127.0.0.1'),
        ],
    ],
    'write' => [
        'host' => [env('DB_WRITE_HOST', '127.0.0.1')],
    ],
    // ... other configuration
],
```

### Optimized Eloquent Models and Relationships

```php
<?php
// app/Models/Workflow.php - Performance optimized model

class Workflow extends Model
{
    // Eager load commonly accessed relationships
    protected $with = ['user:id,username,display_name'];

    // Define relationship queries with constraints
    public function user()
    {
        return $this->belongsTo(User::class)->select(['id', 'username', 'display_name', 'is_verified']);
    }

    public function tags()
    {
        return $this->belongsToMany(WorkflowTag::class, 'workflow_tag_pivot')
                    ->select(['workflow_tags.id', 'name'])
                    ->orderBy('name');
    }

    public function comments()
    {
        return $this->hasMany(WorkflowComment::class)
                    ->whereNull('parent_id')
                    ->with('user:id,username,display_name')
                    ->latest();
    }

    public function likes()
    {
        return $this->hasMany(WorkflowLike::class);
    }

    // Optimized scopes for common queries
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopePopular($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days))
                    ->orderByRaw('(downloads_count * 2 + likes_count * 3 + comments_count * 5) DESC');
    }

    public function scopeWithEngagementCounts($query)
    {
        return $query->withCount(['likes', 'comments', 'downloads as downloads_count']);
    }

    public function scopeForFeed($query, $userIds)
    {
        return $query->whereIn('user_id', $userIds)
                    ->published()
                    ->with(['user:id,username,display_name,is_verified', 'tags:id,name'])
                    ->withEngagementCounts()
                    ->latest();
    }

    // Efficient user interaction checks
    public function isLikedByUser(User $user): bool
    {
        return Cache::remember(
            "workflow_{$this->id}_liked_by_{$user->id}",
            now()->addMinutes(5),
            fn() => $this->likes()->where('user_id', $user->id)->exists()
        );
    }

    public function getUserRating(User $user): ?float
    {
        return Cache::remember(
            "workflow_{$this->id}_rating_by_{$user->id}",
            now()->addMinutes(10),
            fn() => $this->ratings()->where('user_id', $user->id)->value('rating')
        );
    }

    // Batch update counter methods
    public static function updateCountersForWorkflows(array $workflowIds, string $counter, int $amount = 1): void
    {
        static::whereIn('id', $workflowIds)->increment($counter, $amount);
        
        // Clear related cache
        foreach ($workflowIds as $id) {
            Cache::forget("workflow_{$id}_engagement");
        }
    }
}
```

## Caching Strategy

### Multi-Layer Caching Implementation

```php
<?php
// app/Services/CacheService.php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class CacheService
{
    // Cache durations in minutes
    const SHORT_CACHE = 5;
    const MEDIUM_CACHE = 30;
    const LONG_CACHE = 1440; // 24 hours
    const EXTENDED_CACHE = 10080; // 7 days

    /**
     * User activity feed caching
     */
    public function getUserFeed(User $user, int $page = 1): Collection
    {
        $key = "user_feed_{$user->id}_page_{$page}";
        
        return Cache::tags(['feeds', "user_{$user->id}"])->remember(
            $key,
            self::MEDIUM_CACHE,
            function () use ($user, $page) {
                $followingIds = $user->followings()->pluck('id')->push($user->id);
                
                return UserActivity::whereIn('user_id', $followingIds)
                    ->where('is_public', true)
                    ->with(['user', 'activityable'])
                    ->latest()
                    ->paginate(20, ['*'], 'page', $page);
            }
        );
    }

    /**
     * Workflow discovery and trending
     */
    public function getTrendingWorkflows(string $period = '7days'): Collection
    {
        $key = "trending_workflows_{$period}";
        
        return Cache::tags(['trending', 'workflows'])->remember(
            $key,
            self::LONG_CACHE,
            function () use ($period) {
                $days = match($period) {
                    '24h' => 1,
                    '7days' => 7,
                    '30days' => 30,
                    default => 7
                };

                return Workflow::published()
                    ->where('created_at', '>=', now()->subDays($days))
                    ->withEngagementCounts()
                    ->orderByRaw('(downloads_count * 2 + likes_count * 3 + comments_count * 5) DESC')
                    ->limit(100)
                    ->get();
            }
        );
    }

    /**
     * User profile and statistics caching
     */
    public function getUserStats(User $user): array
    {
        $key = "user_stats_{$user->id}";
        
        return Cache::tags(['users', "user_{$user->id}"])->remember(
            $key,
            self::EXTENDED_CACHE,
            function () use ($user) {
                return [
                    'workflows_count' => $user->workflows()->published()->count(),
                    'total_downloads' => $user->workflows()->published()->sum('downloads_count'),
                    'total_likes' => $user->workflows()->published()->sum('likes_count'),
                    'average_rating' => $user->workflows()->published()->avg('average_rating'),
                    'followers_count' => $user->followers()->count(),
                    'following_count' => $user->followings()->count(),
                    'genres_produced' => $user->workflows()->published()
                                             ->whereNotNull('genre')
                                             ->distinct('genre')
                                             ->count('genre'),
                ];
            }
        );
    }

    /**
     * Search results caching
     */
    public function getSearchResults(string $query, array $filters = [], int $page = 1): Collection
    {
        $filterHash = md5(serialize($filters));
        $key = "search_results_" . md5($query) . "_{$filterHash}_page_{$page}";
        
        return Cache::tags(['search'])->remember(
            $key,
            self::SHORT_CACHE,
            function () use ($query, $filters, $page) {
                return app(WorkflowSearchService::class)
                    ->searchWorkflows($query, $filters, $page);
            }
        );
    }

    /**
     * Cache invalidation methods
     */
    public function invalidateUserCache(User $user): void
    {
        Cache::tags(["user_{$user->id}"])->flush();
    }

    public function invalidateWorkflowCache(Workflow $workflow): void
    {
        Cache::tags(['workflows', 'trending', 'feeds'])->flush();
        Cache::forget("workflow_{$workflow->id}_engagement");
    }

    public function invalidateFeedCache(): void
    {
        Cache::tags(['feeds'])->flush();
    }

    public function invalidateSearchCache(): void
    {
        Cache::tags(['search'])->flush();
    }
}
```

### Redis Configuration for High Performance

```php
<?php
// config/cache.php - Redis optimization

'stores' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'cache',
        'lock_connection' => 'cache',
        'serializer' => 'igbinary', // Faster serialization
        'compress' => true, // Compress cached data
    ],

    // Separate store for sessions
    'redis_sessions' => [
        'driver' => 'redis',
        'connection' => 'sessions',
    ],

    // Queue store
    'redis_queue' => [
        'driver' => 'redis',
        'connection' => 'queue',
    ],
],

// config/database.php - Redis connections
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),

    'options' => [
        'cluster' => env('REDIS_CLUSTER', 'redis'),
        'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        'serializer' => Redis::SERIALIZER_IGBINARY,
        'compression' => Redis::COMPRESSION_LZ4,
    ],

    'default' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_DB', '0'),
        'read_write_timeout' => 60,
    ],

    'cache' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_CACHE_DB', '1'),
        'read_write_timeout' => 60,
    ],

    'sessions' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_SESSION_DB', '2'),
        'read_write_timeout' => 60,
    ],

    'queue' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_QUEUE_DB', '3'),
        'read_write_timeout' => 60,
    ],
],
```

## Queue Optimization

### Background Job Processing Strategy

```php
<?php
// config/queue.php - Optimized queue configuration

'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'queue',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 300,
        'block_for' => 5,
        'after_commit' => false,
    ],

    'redis_high' => [
        'driver' => 'redis',
        'connection' => 'queue',
        'queue' => 'high_priority',
        'retry_after' => 120,
        'block_for' => 2,
    ],

    'redis_low' => [
        'driver' => 'redis',
        'connection' => 'queue',
        'queue' => 'low_priority',
        'retry_after' => 600,
        'block_for' => 10,
    ],
],
```

### Optimized Queue Jobs

```php
<?php
// app/Jobs/UpdateWorkflowEngagementMetrics.php

class UpdateWorkflowEngagementMetrics implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;
    public $tries = 3;
    public $uniqueFor = 300; // 5 minutes

    public function __construct(
        public int $workflowId
    ) {
        $this->onQueue('low_priority');
    }

    public function handle(): void
    {
        $workflow = Workflow::find($this->workflowId);
        
        if (!$workflow) {
            return;
        }

        DB::transaction(function () use ($workflow) {
            // Batch update engagement metrics
            $likes = $workflow->likes()->count();
            $comments = $workflow->comments()->count();
            $ratings = $workflow->ratings();
            
            $workflow->update([
                'likes_count' => $likes,
                'comments_count' => $comments,
                'average_rating' => $ratings->avg('rating') ?? 0,
                'ratings_count' => $ratings->count(),
            ]);

            // Clear related cache
            Cache::forget("workflow_{$workflow->id}_engagement");
            Cache::tags(['workflows', 'trending'])->flush();
        });
    }

    public function uniqueId(): string
    {
        return "workflow_metrics_{$this->workflowId}";
    }
}

// app/Jobs/BatchProcessActivityFeed.php
class BatchProcessActivityFeed implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 2;

    public function __construct(
        public array $userIds,
        public UserActivity $activity
    ) {
        $this->onQueue('high_priority');
    }

    public function handle(): void
    {
        // Batch insert feed entries
        $feedEntries = collect($this->userIds)->map(function ($userId) {
            return [
                'user_id' => $userId,
                'activity_id' => $this->activity->id,
                'created_at' => now(),
            ];
        })->chunk(1000);

        // Insert in batches to avoid memory issues
        foreach ($feedEntries as $chunk) {
            UserFeedCache::insert($chunk->toArray());
        }

        // Clear affected user feed caches
        foreach ($this->userIds as $userId) {
            Cache::tags(["user_{$userId}"])->flush();
        }
    }
}
```

## Search Optimization

### Elasticsearch Integration

```php
<?php
// app/Services/WorkflowSearchService.php

use Elasticsearch\Client;

class WorkflowSearchService
{
    public function __construct(
        protected Client $elasticsearch
    ) {}

    public function searchWorkflows(string $query, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $body = $this->buildSearchQuery($query, $filters, $page, $perPage);
        
        $response = $this->elasticsearch->search([
            'index' => 'workflows',
            'body' => $body,
        ]);

        return $this->formatSearchResults($response);
    }

    protected function buildSearchQuery(string $query, array $filters, int $page, int $perPage): array
    {
        $must = [];
        $filter = [];

        // Main search query
        if (!empty($query)) {
            $must[] = [
                'multi_match' => [
                    'query' => $query,
                    'fields' => [
                        'title^3',
                        'description^2',
                        'tags^2',
                        'user.display_name',
                        'devices_used',
                    ],
                    'type' => 'best_fields',
                    'fuzziness' => 'AUTO',
                ]
            ];
        }

        // Apply filters
        if (isset($filters['rack_type'])) {
            $filter[] = ['term' => ['rack_type' => $filters['rack_type']]];
        }

        if (isset($filters['genre'])) {
            $filter[] = ['term' => ['genre.keyword' => $filters['genre']]];
        }

        if (isset($filters['bpm_range'])) {
            [$min, $max] = explode(',', $filters['bpm_range']);
            $filter[] = ['range' => ['bpm' => ['gte' => (int)$min, 'lte' => (int)$max]]];
        }

        if (isset($filters['rating_min'])) {
            $filter[] = ['range' => ['average_rating' => ['gte' => (float)$filters['rating_min']]]];
        }

        // Published workflows only
        $filter[] = ['term' => ['is_published' => true]];

        $body = [
            'query' => [
                'bool' => [
                    'must' => $must,
                    'filter' => $filter,
                ]
            ],
            'sort' => $this->buildSortOptions($filters['sort'] ?? 'relevance'),
            'from' => ($page - 1) * $perPage,
            'size' => $perPage,
            'highlight' => [
                'fields' => [
                    'title' => (object)[],
                    'description' => (object)[],
                ]
            ],
            '_source' => [
                'includes' => [
                    'id', 'title', 'description', 'slug', 'rack_type', 'genre',
                    'average_rating', 'downloads_count', 'likes_count', 'user',
                    'created_at', 'preview_audio_url', 'preview_image_url'
                ]
            ]
        ];

        return $body;
    }

    protected function buildSortOptions(string $sort): array
    {
        return match($sort) {
            'newest' => [['created_at' => ['order' => 'desc']]],
            'oldest' => [['created_at' => ['order' => 'asc']]],
            'rating' => [['average_rating' => ['order' => 'desc']], ['ratings_count' => ['order' => 'desc']]],
            'popular' => [['downloads_count' => ['order' => 'desc']]],
            'trending' => [['likes_count' => ['order' => 'desc']], ['created_at' => ['order' => 'desc']]],
            default => ['_score'] // relevance
        };
    }

    public function indexWorkflow(Workflow $workflow): void
    {
        $this->elasticsearch->index([
            'index' => 'workflows',
            'id' => $workflow->id,
            'body' => [
                'id' => $workflow->id,
                'title' => $workflow->title,
                'description' => $workflow->description,
                'slug' => $workflow->slug,
                'rack_type' => $workflow->rack_type,
                'genre' => $workflow->genre,
                'bpm' => $workflow->bpm,
                'difficulty_level' => $workflow->difficulty_level,
                'average_rating' => $workflow->average_rating,
                'ratings_count' => $workflow->ratings_count,
                'downloads_count' => $workflow->downloads_count,
                'likes_count' => $workflow->likes_count,
                'is_published' => $workflow->is_published,
                'created_at' => $workflow->created_at->toISOString(),
                'tags' => $workflow->tags->pluck('name')->toArray(),
                'devices_used' => $workflow->devices_used ?? [],
                'user' => [
                    'id' => $workflow->user->id,
                    'username' => $workflow->user->username,
                    'display_name' => $workflow->user->display_name,
                ],
            ]
        ]);
    }
}
```

## CDN and Asset Optimization

### CloudFront Configuration

```php
<?php
// app/Services/CDNService.php

class CDNService
{
    protected $cloudfront;
    protected $distributionId;

    public function __construct()
    {
        $this->cloudfront = new \Aws\CloudFront\CloudFrontClient([
            'version' => 'latest',
            'region' => 'us-east-1',
            'credentials' => [
                'key' => config('services.aws.key'),
                'secret' => config('services.aws.secret'),
            ]
        ]);

        $this->distributionId = config('services.cloudfront.distribution_id');
    }

    public function purgeCache(array $paths): void
    {
        $this->cloudfront->createInvalidation([
            'DistributionId' => $this->distributionId,
            'InvalidationBatch' => [
                'CallerReference' => uniqid(),
                'Paths' => [
                    'Quantity' => count($paths),
                    'Items' => $paths,
                ]
            ]
        ]);
    }

    public function generateSignedUrl(string $path, int $expiresInMinutes = 60): string
    {
        $expires = time() + ($expiresInMinutes * 60);
        
        return $this->cloudfront->getSignedUrl([
            'url' => config('services.cloudfront.url') . '/' . ltrim($path, '/'),
            'expires' => $expires,
            'private_key' => file_get_contents(config('services.cloudfront.private_key_path')),
            'key_pair_id' => config('services.cloudfront.key_pair_id'),
        ]);
    }
}
```

## Application-Level Optimization

### Response Caching Middleware

```php
<?php
// app/Http/Middleware/CacheResponse.php

class CacheResponse
{
    public function handle(Request $request, Closure $next, int $minutes = 60)
    {
        // Don't cache for authenticated users or dynamic content
        if ($request->user() || $request->query()) {
            return $next($request);
        }

        $key = 'response_cache_' . md5($request->fullUrl());
        
        return Cache::remember($key, now()->addMinutes($minutes), function () use ($next, $request) {
            $response = $next($request);
            
            // Only cache successful GET requests
            if ($request->isMethod('GET') && $response->status() === 200) {
                return $response;
            }
            
            return $response;
        });
    }
}
```

### Database Query Monitoring

```php
<?php
// app/Providers/AppServiceProvider.php

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Monitor slow queries in production
        if (app()->environment('production')) {
            DB::listen(function (QueryExecuted $query) {
                if ($query->time > 1000) { // Queries taking more than 1 second
                    Log::channel('slow_queries')->warning('Slow Query Detected', [
                        'sql' => $query->sql,
                        'bindings' => $query->bindings,
                        'time' => $query->time,
                        'connection' => $query->connectionName,
                    ]);
                }
            });
        }

        // Enable query log in debug mode
        if (config('app.debug') && config('app.env') !== 'testing') {
            DB::enableQueryLog();
        }
    }
}
```

## Performance Monitoring

### Custom Performance Metrics

```php
<?php
// app/Services/PerformanceMonitoringService.php

class PerformanceMonitoringService
{
    public function trackApiResponse(Request $request, Response $response, float $duration): void
    {
        $metrics = [
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'status_code' => $response->getStatusCode(),
            'duration_ms' => round($duration * 1000, 2),
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'timestamp' => now(),
        ];

        // Store metrics for analysis
        Cache::put(
            "api_metrics_" . now()->format('YmdH') . "_" . uniqid(),
            $metrics,
            now()->addDay()
        );

        // Alert on slow responses
        if ($duration > 2.0) { // 2 seconds
            Log::warning('Slow API Response', $metrics);
        }
    }

    public function getPerformanceStats(string $period = '24h'): array
    {
        $hours = match($period) {
            '1h' => 1,
            '24h' => 24,
            '7d' => 168,
            default => 24
        };

        $pattern = "api_metrics_" . now()->subHours($hours)->format('YmdH') . "*";
        $keys = Redis::keys($pattern);
        $metrics = collect($keys)->map(fn($key) => Cache::get($key))->filter();

        return [
            'total_requests' => $metrics->count(),
            'average_response_time' => $metrics->avg('duration_ms'),
            'p95_response_time' => $metrics->sortBy('duration_ms')->values()->get(intval($metrics->count() * 0.95))?->duration_ms,
            'error_rate' => $metrics->where('status_code', '>=', 400)->count() / max($metrics->count(), 1),
            'slowest_endpoints' => $metrics->sortByDesc('duration_ms')->take(10)->values(),
        ];
    }
}
```

## Production Configuration

### Optimized PHP Configuration

```ini
; php.ini optimizations for Laravel social media app

memory_limit = 512M
max_execution_time = 60
max_input_time = 60
post_max_size = 100M
upload_max_filesize = 50M

; OPcache settings
opcache.enable = 1
opcache.enable_cli = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 64
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 0
opcache.validate_timestamps = 0
opcache.save_comments = 1
opcache.fast_shutdown = 1

; Session optimization
session.cache_limiter = nocache
session.cookie_httponly = 1
session.use_strict_mode = 1
session.gc_maxlifetime = 7200

; Redis session handler
session.save_handler = redis
session.save_path = "tcp://127.0.0.1:6379?database=2"
```

### Laravel Optimization Commands

```bash
# Production optimization commands
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan queue:restart

# Database optimizations
php artisan migrate --force
php artisan db:seed --class=ProductionSeeder

# Clear and rebuild caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear

# Asset optimization
npm run production
php artisan storage:link
```

This comprehensive performance optimization strategy provides:

- Database indexing and query optimization
- Multi-layer caching with Redis
- Search optimization with Elasticsearch
- CDN integration for global content delivery
- Background job processing with proper queuing
- Response caching and monitoring
- Production-ready PHP configuration

The implementation focuses on the unique performance challenges of social media platforms: high read/write ratios, real-time interactions, search functionality, and media delivery at scale.