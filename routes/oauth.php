<?php

use App\Http\Controllers\OAuth\AuthorizationController;
use Illuminate\Support\Facades\Route;

/*
| OAuth authorization endpoint (web group: session, CSRF). Guests sign in first and come back here
| through the intended URL. See docs/front/README.md (Website editor and MCP).
*/

Route::middleware('auth')->group(function () {
    Route::get('oauth/authorize', [AuthorizationController::class, 'show'])->name('oauth.authorize');
    Route::post('oauth/authorize', [AuthorizationController::class, 'store'])->name('oauth.authorize.store');
});
