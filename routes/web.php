<?php

use App\Auth\AuthMethodResolver;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TranslateController;
use App\Http\Controllers\Admin\UploadController;
use App\Http\Controllers\Auth\AccountAuthController;
use App\Http\Controllers\Auth\KeycloakAuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\LocaleController;
use App\Models\Setting;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/search', [HomeController::class, 'search'])->name('search');
Route::get('/feed', [FeedController::class, 'atom'])->name('feed.atom');
Route::get('/rss', [FeedController::class, 'rss'])->name('feed.rss');
Route::get('/json', [FeedController::class, 'json'])->name('feed.json');

// Canonical post URL: /p/{uuid} (prefix configurable in settings)
$postPrefix = 'p';
try {
    $configured = Setting::get('post_path_prefix');
    if ($configured && preg_match('/^[a-z0-9_-]+$/', $configured)) {
        $postPrefix = $configured;
    }
} catch (\Throwable) {
    // Settings table may not exist yet
}

Route::get('/'.$postPrefix.'/{uuid}', [ImageController::class, 'show'])->name('image.show');

// Backwards-compatible redirect from old /image/ URLs
Route::get('/image/{uuid}', function (string $uuid) {
    return redirect()->route('image.show', ['uuid' => $uuid], 301);
});

Route::get('/lang/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

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
    Route::get('/images/{uuid}/edit', [UploadController::class, 'edit'])->name('admin.images.edit');
    Route::put('/images/{uuid}', [UploadController::class, 'update'])->name('admin.images.update');
    Route::delete('/images/{uuid}', [UploadController::class, 'destroy'])->name('admin.images.destroy');
    Route::get('/categories', [CategoryController::class, 'index'])->name('admin.categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->name('admin.categories.store');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('admin.categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('admin.categories.destroy');
    Route::get('/settings', [SettingsController::class, 'edit'])->name('admin.settings.edit');
    Route::post('/settings', [SettingsController::class, 'update'])->name('admin.settings.update');
    Route::post('/translate', [TranslateController::class, 'translate'])->name('admin.translate');
});
