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
        return view('racks');
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
    Route::get('/racks/{rack}/edit', [App\Http\Controllers\RackEditController::class, 'edit'])->name('racks.edit');
    Route::put('/racks/{rack}', [App\Http\Controllers\RackEditController::class, 'update'])->name('racks.update');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
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
