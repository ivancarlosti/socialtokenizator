<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\PostController;
use Illuminate\Support\Facades\Route;

Route::middleware('api.token')->group(function () {
    Route::get('/categories', [CategoryController::class, 'index'])->name('api.categories.index');
    Route::post('/posts', [PostController::class, 'store'])->name('api.posts.store');
    Route::get('/posts', [PostController::class, 'index'])->name('api.posts.index');
    Route::get('/posts/{uuid}', [PostController::class, 'show'])->name('api.posts.show');
    Route::put('/posts/{uuid}', [PostController::class, 'update'])->name('api.posts.update');
    Route::delete('/posts/{uuid}', [PostController::class, 'destroy'])->name('api.posts.destroy');
});
