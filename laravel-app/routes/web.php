<?php

use Illuminate\Support\Facades\Route;

// Health check for Railway
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'app' => config('app.name'),
        'env' => config('app.env')
    ]);
});

Route::get('/', function () {
    try {
        $recentBlogPosts = \App\Models\BlogPost::with(['author', 'category'])
            ->published()
            ->latest('published_at')
            ->take(3)
            ->get();
            
        return view('racks', compact('recentBlogPosts'));
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'View loading failed',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
})->name('home');

Route::get('/racks/{rack}', function (App\Models\Rack $rack) {
    return view('rack-show', ['rack' => $rack]);
})->name('racks.show');

Route::get('/profile', function () {
    return view('profile', ['user' => auth()->user()]);
})->middleware('auth')->name('profile');

Route::get('/users/{user}', function (App\Models\User $user) {
    return view('profile', ['user' => $user]);
})->name('users.show');

// Upload routes (require authentication)
Route::middleware('auth')->group(function () {
    Route::get('/upload', [App\Http\Controllers\RackUploadController::class, 'create'])->name('racks.upload');
    Route::post('/upload', [App\Http\Controllers\RackUploadController::class, 'store'])->name('racks.store');
    
    // Edit routes (require authentication and ownership)
    // Edit routes (require authentication and ownership) - Multi-step process
    Route::get("/racks/{rack}/edit", [App\Http\Controllers\RackEditController::class, "edit"])->name("racks.edit");
    Route::get("/racks/{rack}/edit/upload", [App\Http\Controllers\RackEditController::class, "editUpload"])->name("racks.edit.upload");
    Route::post("/racks/{rack}/edit/upload", [App\Http\Controllers\RackEditController::class, "processUpload"])->name("racks.edit.upload.process");
    Route::get("/racks/{rack}/edit/analysis", [App\Http\Controllers\RackEditController::class, "editAnalysis"])->name("racks.edit.analysis");
    Route::get("/racks/{rack}/edit/annotate", [App\Http\Controllers\RackEditController::class, "editAnnotate"])->name("racks.edit.annotate");
    Route::post("/racks/{rack}/edit/annotate", [App\Http\Controllers\RackEditController::class, "saveAnnotations"])->name("racks.edit.annotate.save");
    Route::get("/racks/{rack}/edit/metadata", [App\Http\Controllers\RackEditController::class, "editMetadata"])->name("racks.edit.metadata");
    Route::put("/racks/{rack}", [App\Http\Controllers\RackEditController::class, "update"])->name("racks.update");
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // Demo Routes
    Route::get('/demos/rack-tree-vertical', function () {
        return view('demos.rack-tree-vertical');
    })->name('demos.rack-tree-vertical');

    Route::get('/demos/rack-tree-horizontal', function () {
        return view('demos.rack-tree-horizontal');
    })->name('demos.rack-tree-horizontal');
});

// Blog Routes (Public)
Route::prefix('blog')->name('blog.')->group(function () {
    Route::get('/', [App\Http\Controllers\BlogController::class, 'index'])->name('index');
    Route::get('/search', [App\Http\Controllers\BlogController::class, 'search'])->name('search');
    Route::get('/category/{slug}', [App\Http\Controllers\BlogController::class, 'category'])->name('category');
    Route::get('/rss', [App\Http\Controllers\BlogController::class, 'rss'])->name('rss');
    Route::get('/sitemap.xml', [App\Http\Controllers\BlogController::class, 'sitemap'])->name('sitemap');
    Route::get('/{slug}', [App\Http\Controllers\BlogController::class, 'show'])->name('show');
});

// Blog Admin Routes (Require authentication and admin role)
Route::middleware(['auth', 'admin'])->prefix('admin/blog')->name('admin.blog.')->group(function () {
    // Blog Posts
    Route::get('/', [App\Http\Controllers\Admin\BlogAdminController::class, 'index'])->name('index');
    Route::get('/create', [App\Http\Controllers\Admin\BlogAdminController::class, 'create'])->name('create');
    Route::post('/', [App\Http\Controllers\Admin\BlogAdminController::class, 'store'])->name('store');
    Route::get('/{post}', [App\Http\Controllers\Admin\BlogAdminController::class, 'show'])->name('show');
    Route::get('/{post}/edit', [App\Http\Controllers\Admin\BlogAdminController::class, 'edit'])->name('edit');
    Route::put('/{post}', [App\Http\Controllers\Admin\BlogAdminController::class, 'update'])->name('update');
    Route::delete('/{post}', [App\Http\Controllers\Admin\BlogAdminController::class, 'destroy'])->name('destroy');
    
    // AJAX Routes
    Route::post('/upload-image', [App\Http\Controllers\Admin\BlogAdminController::class, 'uploadImage'])->name('upload-image');
    Route::post('/{post}/toggle-featured', [App\Http\Controllers\Admin\BlogAdminController::class, 'toggleFeatured'])->name('toggle-featured');
    Route::post('/{post}/toggle-active', [App\Http\Controllers\Admin\BlogAdminController::class, 'toggleActive'])->name('toggle-active');

    // Blog Categories
    Route::prefix('categories')->name('categories.')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\BlogCategoryController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\Admin\BlogCategoryController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\Admin\BlogCategoryController::class, 'store'])->name('store');
        Route::get('/{category}/edit', [App\Http\Controllers\Admin\BlogCategoryController::class, 'edit'])->name('edit');
        Route::put('/{category}', [App\Http\Controllers\Admin\BlogCategoryController::class, 'update'])->name('update');
        Route::delete('/{category}', [App\Http\Controllers\Admin\BlogCategoryController::class, 'destroy'])->name('destroy');
        Route::post('/{category}/toggle-active', [App\Http\Controllers\Admin\BlogCategoryController::class, 'toggleActive'])->name('toggle-active');
    });
});

// SEO Routes
Route::get('/sitemap.xml', [App\Http\Controllers\SitemapController::class, 'index'])->name('sitemap.index');
Route::get('/sitemap-static.xml', [App\Http\Controllers\SitemapController::class, 'static'])->name('sitemap.static');
Route::get('/sitemap-racks.xml', [App\Http\Controllers\SitemapController::class, 'racks'])->name('sitemap.racks');
Route::get('/sitemap-users.xml', [App\Http\Controllers\SitemapController::class, 'users'])->name('sitemap.users');

// SEO Webhook Routes (for automated sitemap refresh)
Route::prefix('seo')->group(function () {
    Route::post('/refresh-sitemap', [App\Http\Controllers\SeoWebhookController::class, 'refreshSitemap'])->name('seo.refresh');
    Route::get('/ping', [App\Http\Controllers\SeoWebhookController::class, 'ping'])->name('seo.ping');
});

// Multi-step upload workflow
Route::middleware('auth')->group(function () {
    Route::get('/racks/{rack}/analysis', [App\Http\Controllers\RackUploadController::class, 'analysis'])->name('racks.analysis');
    Route::get('/racks/{rack}/annotate', [App\Http\Controllers\RackAnnotationController::class, 'annotate'])->name('racks.annotate');
    Route::post('/racks/{rack}/annotate', [App\Http\Controllers\RackAnnotationController::class, 'saveAnnotations'])->name('racks.annotate.save');
    Route::get('/racks/{rack}/metadata', [App\Http\Controllers\RackAnnotationController::class, 'metadata'])->name('racks.metadata');
    Route::post('/racks/{rack}/publish', [App\Http\Controllers\RackAnnotationController::class, 'publish'])->name('racks.publish');
});

// Issue Reporting System Routes
Route::prefix('issues')->name('issues.')->group(function () {
    // Public routes
    Route::get('/', [App\Http\Controllers\IssueController::class, 'index'])->name('index');
    Route::get('/create', [App\Http\Controllers\IssueController::class, 'create'])->name('create');
    Route::post('/', [App\Http\Controllers\IssueController::class, 'store'])->name('store');
    Route::get('/{issue}', [App\Http\Controllers\IssueController::class, 'show'])->name('show');
    
    // Authenticated routes
    Route::middleware('auth')->group(function () {
        Route::post('/{issue}/comments', [App\Http\Controllers\IssueController::class, 'addComment'])->name('comments.store');
    });
});

// Admin issue management routes
Route::middleware(['auth', 'admin'])->prefix('admin/issues')->name('admin.issues.')->group(function () {
    Route::get('/', [App\Http\Controllers\IssueController::class, 'adminIndex'])->name('index');
    Route::get('/{issue}', [App\Http\Controllers\IssueController::class, 'adminShow'])->name('show');
    Route::patch('/{issue}', [App\Http\Controllers\IssueController::class, 'update'])->name('update');
});

// Quick report routes for specific racks
Route::get('/racks/{rack}/report', [App\Http\Controllers\IssueController::class, 'create'])
    ->name('racks.report');

// Temporary public demo routes for testing
Route::get('/public-demos/rack-tree-vertical', function () {
    return view('demos.rack-tree-vertical');
})->name('public-demos.rack-tree-vertical');

Route::get('/public-demos/rack-tree-horizontal', function () {
    return view('demos.rack-tree-horizontal');
})->name('public-demos.rack-tree-horizontal');

