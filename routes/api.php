<?php

use App\Http\Controllers\Api\PostController;
use Illuminate\Support\Facades\Route;

Route::middleware('api.token')->group(function () {
    Route::post('/posts', [PostController::class, 'store'])->name('api.posts.store');
    Route::get('/posts', [PostController::class, 'index'])->name('api.posts.index');
    Route::get('/posts/{uuid}', [PostController::class, 'show'])->name('api.posts.show');
    Route::delete('/posts/{uuid}', [PostController::class, 'destroy'])->name('api.posts.destroy');
});
