<?php

use App\Auth\AuthMethodResolver;
use App\Http\Controllers\Admin\UploadController;
use App\Http\Controllers\Auth\AccountAuthController;
use App\Http\Controllers\Auth\KeycloakAuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/search', [HomeController::class, 'search'])->name('search');
Route::get('/image/{uuid}', [ImageController::class, 'show'])->name('image.show');

// Auth routes — registered conditionally based on AUTH_METHOD
$method = AuthMethodResolver::current();

if ($method === AuthMethodResolver::ACCOUNT) {
    Route::get('/auth/login', [AccountAuthController::class, 'showLogin'])->name('auth.login.show');
    Route::post('/auth/login', [AccountAuthController::class, 'login'])->name('auth.login');
    Route::post('/auth/logout', [AccountAuthController::class, 'logout'])->name('auth.logout');
}

if ($method === AuthMethodResolver::KEYCLOAK) {
    Route::get('/auth/keycloak/redirect', [KeycloakAuthController::class, 'redirect'])->name('auth.keycloak.redirect');
    Route::get('/auth/keycloak/callback', [KeycloakAuthController::class, 'callback'])->name('auth.keycloak.callback');
    Route::post('/auth/logout', [KeycloakAuthController::class, 'logout'])->name('auth.logout');
}

Route::middleware('admin')->prefix('admin')->group(function () {
    Route::get('/', [UploadController::class, 'create'])->name('admin.upload.create');
    Route::post('/upload', [UploadController::class, 'store'])->name('admin.upload.store');
    Route::delete('/images/{uuid}', [UploadController::class, 'destroy'])->name('admin.images.destroy');
});
