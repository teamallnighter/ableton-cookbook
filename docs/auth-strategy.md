# Authentication & Authorization Strategy for Ableton Cookbook

## Authentication Architecture

### Multi-Guard Authentication Setup

```php
// config/auth.php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    'api' => [
        'driver' => 'sanctum',
        'provider' => 'users',
        'hash' => false,
    ],
],

'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => App\Models\User::class,
    ],
],
```

### User Model with Social Features

```php
<?php
// app/Models/User.php

use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Overtrue\LaravelFollow\Traits\Follower;
use Overtrue\LaravelFollow\Traits\Followable;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class User extends Authenticatable implements HasMedia
{
    use HasApiTokens, 
        HasFactory, 
        Notifiable,
        HasRoles,
        Follower,
        Followable,
        LogsActivity,
        InteractsWithMedia;

    protected $fillable = [
        'username',
        'email',
        'password',
        'display_name',
        'bio',
        'location',
        'website',
        'is_verified',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'email_verified_at',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_active_at' => 'datetime',
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function workflows()
    {
        return $this->hasMany(Workflow::class);
    }

    public function ratings()
    {
        return $this->hasMany(WorkflowRating::class);
    }

    public function comments()
    {
        return $this->hasMany(WorkflowComment::class);
    }

    public function likes()
    {
        return $this->hasMany(WorkflowLike::class);
    }

    public function collections()
    {
        return $this->hasMany(WorkflowCollection::class);
    }

    // Media Collections
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(150)
            ->height(150)
            ->sharpen(10);
        
        $this->addMediaConversion('profile')
            ->width(300)
            ->height(300)
            ->sharpen(10);
    }

    // Accessors
    public function getAvatarUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('avatar', 'profile') ?: null;
    }

    public function getAvatarThumbUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('avatar', 'thumb') ?: null;
    }

    // Activity Log
    protected static $logAttributes = ['username', 'email', 'display_name'];
    protected static $logName = 'user';
    protected static $logOnlyDirty = true;

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeWithCounts($query)
    {
        return $query->withCount(['workflows', 'followers', 'followings']);
    }
}
```

## Role-Based Authorization

### Roles and Permissions Setup

```php
<?php
// database/seeders/RolePermissionSeeder.php

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run()
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Workflow permissions
            'create-workflows',
            'edit-own-workflows',
            'edit-any-workflows',
            'delete-own-workflows',
            'delete-any-workflows',
            'publish-workflows',
            'feature-workflows',
            
            // User permissions
            'edit-own-profile',
            'edit-any-profile',
            'ban-users',
            'verify-users',
            
            // Comment permissions
            'create-comments',
            'edit-own-comments',
            'edit-any-comments',
            'delete-own-comments',
            'delete-any-comments',
            'pin-comments',
            
            // System permissions
            'access-admin-panel',
            'view-analytics',
            'manage-tags',
            'manage-collections',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles
        $superAdmin = Role::create(['name' => 'super-admin']);
        $admin = Role::create(['name' => 'admin']);
        $moderator = Role::create(['name' => 'moderator']);
        $verified = Role::create(['name' => 'verified-user']);
        $user = Role::create(['name' => 'user']);

        // Assign permissions to roles
        $superAdmin->givePermissionTo(Permission::all());

        $admin->givePermissionTo([
            'edit-any-workflows',
            'delete-any-workflows',
            'feature-workflows',
            'edit-any-profile',
            'ban-users',
            'verify-users',
            'edit-any-comments',
            'delete-any-comments',
            'pin-comments',
            'access-admin-panel',
            'view-analytics',
            'manage-tags',
            'manage-collections',
        ]);

        $moderator->givePermissionTo([
            'edit-any-workflows',
            'delete-any-workflows',
            'edit-any-comments',
            'delete-any-comments',
            'pin-comments',
            'ban-users',
            'manage-tags',
        ]);

        $verified->givePermissionTo([
            'create-workflows',
            'edit-own-workflows',
            'delete-own-workflows',
            'publish-workflows',
            'edit-own-profile',
            'create-comments',
            'edit-own-comments',
            'delete-own-comments',
            'manage-collections',
        ]);

        $user->givePermissionTo([
            'create-workflows',
            'edit-own-workflows',
            'delete-own-workflows',
            'edit-own-profile',
            'create-comments',
            'edit-own-comments',
            'delete-own-comments',
        ]);
    }
}
```

### Policy-Based Authorization

```php
<?php
// app/Policies/WorkflowPolicy.php

class WorkflowPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true; // Public platform
    }

    public function view(?User $user, Workflow $workflow): bool
    {
        if (!$workflow->is_published) {
            return $user && ($user->id === $workflow->user_id || $user->can('edit-any-workflows'));
        }
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('create-workflows') && $user->is_active;
    }

    public function update(User $user, Workflow $workflow): bool
    {
        return $user->can('edit-any-workflows') || 
               ($user->can('edit-own-workflows') && $user->id === $workflow->user_id);
    }

    public function delete(User $user, Workflow $workflow): bool
    {
        return $user->can('delete-any-workflows') || 
               ($user->can('delete-own-workflows') && $user->id === $workflow->user_id);
    }

    public function publish(User $user, Workflow $workflow): bool
    {
        return $user->can('publish-workflows') && 
               ($user->id === $workflow->user_id || $user->can('edit-any-workflows'));
    }

    public function feature(User $user, Workflow $workflow): bool
    {
        return $user->can('feature-workflows');
    }

    public function download(?User $user, Workflow $workflow): bool
    {
        return $workflow->is_published;
    }
}
```

## API Authentication with Sanctum

### Token-Based API Authentication

```php
<?php
// app/Http/Controllers/Auth/ApiAuthController.php

class ApiAuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'display_name' => $request->display_name,
        ]);

        // Assign default role
        $user->assignRole('user');

        // Send email verification
        $user->sendEmailVerificationNotification();

        $token = $user->createToken('auth-token', ['*'], now()->addDays(30));

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at,
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        $user = Auth::user();

        if (!$user->is_active) {
            return response()->json([
                'message' => 'Account is deactivated'
            ], 403);
        }

        // Revoke old tokens (optional - for single session)
        // $user->tokens()->delete();

        $token = $user->createToken('auth-token', ['*'], now()->addDays(30));

        // Update last active timestamp
        $user->update(['last_active_at' => now()]);

        return response()->json([
            'user' => new UserResource($user->load('roles')),
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    }

    public function profile(Request $request)
    {
        return new UserResource($request->user()->load('roles', 'permissions'));
    }
}
```

### Middleware for Rate Limiting and Security

```php
<?php
// app/Http/Middleware/UpdateLastActive.php

class UpdateLastActive
{
    public function handle($request, Closure $next)
    {
        if (auth()->check()) {
            auth()->user()->update(['last_active_at' => now()]);
        }

        return $next($request);
    }
}

// app/Http/Middleware/EnsureUserIsActive.php
class EnsureUserIsActive
{
    public function handle($request, Closure $next)
    {
        if (auth()->check() && !auth()->user()->is_active) {
            auth()->logout();
            
            return response()->json([
                'message' => 'Your account has been deactivated.'
            ], 403);
        }

        return $next($request);
    }
}
```

### Route Protection Strategy

```php
<?php
// routes/api.php

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('register', [ApiAuthController::class, 'register']);
    Route::post('login', [ApiAuthController::class, 'login']);
    Route::post('forgot-password', [PasswordResetController::class, 'forgotPassword']);
});

// Public workflow browsing
Route::get('workflows', [WorkflowController::class, 'index']);
Route::get('workflows/{workflow:slug}', [WorkflowController::class, 'show']);
Route::get('users/{user:username}', [UserController::class, 'show']);

// Protected routes
Route::middleware(['auth:sanctum', 'user.active', 'update.last.active'])->group(function () {
    // User routes
    Route::get('auth/profile', [ApiAuthController::class, 'profile']);
    Route::post('auth/logout', [ApiAuthController::class, 'logout']);
    Route::put('profile', [UserController::class, 'updateProfile']);
    
    // Workflow management
    Route::apiResource('workflows', WorkflowController::class)->except(['index', 'show']);
    Route::post('workflows/{workflow}/download', [WorkflowController::class, 'download']);
    Route::post('workflows/{workflow}/like', [WorkflowInteractionController::class, 'like']);
    Route::delete('workflows/{workflow}/like', [WorkflowInteractionController::class, 'unlike']);
    
    // Social features
    Route::post('users/{user}/follow', [FollowController::class, 'follow']);
    Route::delete('users/{user}/follow', [FollowController::class, 'unfollow']);
    
    // Comments
    Route::apiResource('workflows.comments', WorkflowCommentController::class)
          ->except(['index', 'show']);
    
    // Collections
    Route::apiResource('collections', WorkflowCollectionController::class);
});

// Admin routes
Route::middleware(['auth:sanctum', 'user.active', 'role:admin|moderator'])->prefix('admin')->group(function () {
    Route::get('dashboard', [AdminController::class, 'dashboard']);
    Route::apiResource('users', AdminUserController::class);
    Route::post('workflows/{workflow}/feature', [AdminWorkflowController::class, 'feature']);
    Route::delete('workflows/{workflow}/feature', [AdminWorkflowController::class, 'unfeature']);
});
```

## Security Best Practices

### Input Validation and Sanitization

```php
<?php
// app/Http/Requests/WorkflowRequest.php

class WorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create-workflows');
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255|min:3',
            'description' => 'nullable|string|max:5000',
            'rack_type' => 'required|in:audio_effect,instrument,midi_effect',
            'genre' => 'nullable|string|max:100',
            'bpm' => 'nullable|integer|min:60|max:200',
            'key_signature' => 'nullable|string|max:10',
            'difficulty_level' => 'nullable|in:beginner,intermediate,advanced',
            'adg_file' => 'required|file|mimes:adg|max:10240', // 10MB max
            'preview_audio' => 'nullable|file|mimes:mp3,wav,m4a|max:20480', // 20MB max
            'tags' => 'nullable|array|max:10',
            'tags.*' => 'string|max:50|regex:/^[a-zA-Z0-9\-_\s]+$/',
        ];
    }

    public function messages(): array
    {
        return [
            'adg_file.mimes' => 'The file must be an Ableton Device Group (.adg) file.',
            'tags.*.regex' => 'Tags can only contain letters, numbers, hyphens, underscores, and spaces.',
        ];
    }
}
```

### Rate Limiting Configuration

```php
<?php
// app/Providers/RouteServiceProvider.php

protected function configureRateLimiting()
{
    RateLimiter::for('api', function (Request $request) {
        return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
    });

    RateLimiter::for('uploads', function (Request $request) {
        return Limit::perHour(10)->by(optional($request->user())->id ?: $request->ip());
    });

    RateLimiter::for('downloads', function (Request $request) {
        return Limit::perDay(100)->by(optional($request->user())->id ?: $request->ip());
    });

    RateLimiter::for('social', function (Request $request) {
        return Limit::perMinute(30)->by(optional($request->user())->id ?: $request->ip());
    });
}
```

## Session Management

### Custom Session Configuration

```php
// config/session.php
return [
    'lifetime' => env('SESSION_LIFETIME', 120),
    'expire_on_close' => false,
    'encrypt' => true,
    'files' => storage_path('framework/sessions'),
    'connection' => env('SESSION_CONNECTION'),
    'table' => 'sessions',
    'store' => env('SESSION_STORE'),
    'lottery' => [2, 100],
    'cookie' => env('SESSION_COOKIE', 'ableton_cookbook_session'),
    'path' => '/',
    'domain' => env('SESSION_DOMAIN'),
    'secure' => env('SESSION_SECURE_COOKIE'),
    'http_only' => true,
    'same_site' => 'lax',
];
```

This authentication strategy provides:
- Multi-guard authentication for web and API
- Comprehensive role-based permissions
- Policy-based authorization
- Secure API token management
- Rate limiting and security middleware
- Input validation and sanitization
- Proper session management

The system is designed to scale with your social media platform while maintaining security and performance.