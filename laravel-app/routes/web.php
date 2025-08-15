<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('racks');
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
