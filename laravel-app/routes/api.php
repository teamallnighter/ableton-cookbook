  <?php

  use App\Http\Controllers\Api\RackController;
  use App\Http\Controllers\Api\RackRatingController;
  use App\Http\Controllers\Api\UserController;
  use App\Http\Controllers\Api\CommentController;
  use App\Http\Controllers\Api\CollectionController;
  use App\Http\Controllers\Api\AuthController;
  use Illuminate\Http\Request;
  use Illuminate\Support\Facades\Route;

  Route::get('/user', function (Request $request) {
      return $request->user();
  })->middleware('auth:sanctum');

  // Authentication routes for desktop apps
  Route::prefix('v1/auth')->group(function () {
      Route::post('/login', [AuthController::class, 'login']);
      Route::post('/logout', [AuthController::class,
  'logout'])->middleware('auth:sanctum');
      Route::get('/user', [AuthController::class,
  'user'])->middleware('auth:sanctum');
  });

  // Public routes with rate limiting
  Route::prefix('v1')->middleware(['throttle:60,1'])->group(function () {
      // Racks - Public endpoints
      Route::get('/racks', [RackController::class, 'index']);
      Route::get('/racks/trending', [RackController::class, 'trending']);
      Route::get('/racks/featured', [RackController::class, 'featured']);
      Route::get('/racks/{rack}', [RackController::class, 'show']);

      // Users - Public profiles
      Route::get('/users/{user}', [UserController::class, 'show']);
      Route::get('/users/{user}/racks', [UserController::class, 'racks']);
      Route::get('/users/{user}/followers', [UserController::class,
  'followers']);
      Route::get('/users/{user}/following', [UserController::class,
  'following']);
  });

  // Authenticated routes with stricter rate limiting
  Route::prefix('v1')->middleware(['auth:sanctum',
  'throttle:120,1'])->group(function () {
      // Racks - Authenticated actions with specific limits
      Route::post('/racks', [RackController::class,
  'store'])->middleware('throttle:5,1');
      Route::put('/racks/{rack}', [RackController::class, 'update']);
      Route::delete('/racks/{rack}', [RackController::class, 'destroy']);
      Route::post('/racks/{rack}/download', [RackController::class,
  'download'])->middleware('throttle:30,1');
      Route::post('/racks/{rack}/like', [RackController::class,
  'toggleLike']);

      // Rack Ratings
      Route::post('/racks/{rack}/rate', [RackRatingController::class,
  'store']);
      Route::put('/racks/{rack}/rate', [RackRatingController::class,
  'update']);
      Route::delete('/racks/{rack}/rate', [RackRatingController::class,
  'destroy']);

      // Comments
      Route::get('/racks/{rack}/comments', [CommentController::class,
  'index']);
      Route::post('/racks/{rack}/comments', [CommentController::class,
  'store']);
      Route::put('/comments/{comment}', [CommentController::class,
  'update']);
      Route::delete('/comments/{comment}', [CommentController::class,
  'destroy']);
      Route::post('/comments/{comment}/like', [CommentController::class,
  'toggleLike']);

      // User actions
      Route::post('/users/{user}/follow', [UserController::class,
  'follow']);
      Route::delete('/users/{user}/follow', [UserController::class,
  'unfollow']);
      Route::get('/user/feed', [UserController::class, 'feed']);
      Route::get('/user/notifications', [UserController::class,
  'notifications']);

      // Collections
      Route::get('/collections', [CollectionController::class, 'index']);
      Route::post('/collections', [CollectionController::class, 'store']);
      Route::get('/collections/{collection}', [CollectionController::class,
  'show']);
      Route::put('/collections/{collection}', [CollectionController::class,
  'update']);
      Route::delete('/collections/{collection}',
  [CollectionController::class, 'destroy']);
      Route::post('/collections/{collection}/racks/{rack}',
  [CollectionController::class, 'addRack']);
      Route::delete('/collections/{collection}/racks/{rack}',
  [CollectionController::class, 'removeRack']);
  });

