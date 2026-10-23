<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\PluginAdminController;
use App\Http\Controllers\Admin\SiteSettingsAdminController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\SetPortalPasswordController;
use App\Http\Controllers\CustomerChangeRequestController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Portal\PortalCustomerChangeRequestController;
use App\Http\Controllers\Portal\PortalHomeController;
use App\Http\Controllers\PortalUserController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// The public site lives under the front prefix; the root sends visitors there (or to login when no front is mounted).
Route::get('/', function () {
    $publicHomeUrl = config('app.public_home_url');

    if (is_string($publicHomeUrl) && preg_match('#^https?://#i', $publicHomeUrl) === 1) {
        return redirect()->away($publicHomeUrl);
    }

    return Route::has('front.home') ? redirect()->route('front.home') : redirect()->route('login');
})->name('home');

// Served by Laravel (not a static file) so it gets the manifest content type without an nginx change.
Route::get('site.webmanifest', fn () => response()->json([
    'name' => config('app.name'),
    'short_name' => config('app.name'),
    'start_url' => '/',
    'display' => 'browser',
    'background_color' => '#201e1d',
    'theme_color' => '#201e1d',
    'icons' => [
        ['src' => '/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
        ['src' => '/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
        ['src' => '/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=86400'], JSON_UNESCAPED_SLASHES))->name('webmanifest');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:login');
});

Route::middleware('signed')->group(function () {
    Route::get('portal/password/{user}', [SetPortalPasswordController::class, 'create'])
        ->name('portal.password.create');
    Route::post('portal/password/{user}', [SetPortalPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('portal.password.store');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('account', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('account/password', [AccountController::class, 'updatePassword'])
        ->middleware('throttle:6,1')
        ->name('account.password.update');

    Route::middleware('can:staff.access')->group(function () {
        Route::resource('customers', CustomerController::class);
        Route::get('client-requests', [CustomerChangeRequestController::class, 'index'])->name('client-requests.index');
        Route::post('customers/{customer}/portal-users', [PortalUserController::class, 'store'])->name('customers.portal-users.store');
        Route::delete('customers/{customer}/portal-users/{user}', [PortalUserController::class, 'destroy'])->name('customers.portal-users.destroy');
        Route::resource('users', UserController::class)->only(['index', 'create', 'store', 'edit', 'update']);
        Route::post('users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
        Route::post('users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');
    });

    Route::middleware('can:portal.access')->prefix('portal')->name('portal.')->group(function () {
        Route::get('/', PortalHomeController::class)->name('home');
        Route::post('customers/{customer}/change-requests', [PortalCustomerChangeRequestController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('customers.change-requests.store');
    });

    Route::middleware('can:manage-plugins')->prefix('admin')->name('admin.')->group(function () {
        Route::redirect('features', '/admin/plugins');
        Route::get('plugins', [PluginAdminController::class, 'index'])->name('plugins.index');
        Route::post('plugins/{plugin:slug}/disable', [PluginAdminController::class, 'disable'])->name('plugins.disable');
        Route::post('plugins/{plugin:slug}/enable', [PluginAdminController::class, 'enable'])->name('plugins.enable');
    });

    Route::middleware('can:manage-site')->prefix('admin')->name('admin.')->group(function () {
        Route::get('settings', [SiteSettingsAdminController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SiteSettingsAdminController::class, 'update'])->name('settings.update');
    });
});
