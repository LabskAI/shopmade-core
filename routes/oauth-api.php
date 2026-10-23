<?php

use App\Core\OAuth\CrossOriginHeaders;
use App\Http\Controllers\OAuth\AuthorizationServerMetadataController;
use App\Http\Controllers\OAuth\ClientRegistrationController;
use App\Http\Controllers\OAuth\PreflightController;
use App\Http\Controllers\OAuth\ProtectedResourceMetadataController;
use App\Http\Controllers\OAuth\TokenController;
use App\Http\Controllers\OAuth\TokenRevocationController;
use Illuminate\Support\Facades\Route;

/*
| Stateless OAuth endpoints (no session, cookies or CSRF): registered from bootstrap/app.php `then:`.
| Every route here sends CORS headers and answers preflight requests. See docs/front/README.md (Website editor and MCP).
*/

Route::middleware(CrossOriginHeaders::class)->group(function () {
    $resourceMetadata = '.well-known/oauth-protected-resource';
    $mcpResourceMetadata = $resourceMetadata.'/'.trim((string) config('agents.mcp.path'), '/');

    Route::get($resourceMetadata, ProtectedResourceMetadataController::class)->name('oauth.protected-resource');
    Route::get($mcpResourceMetadata, ProtectedResourceMetadataController::class)->name('oauth.protected-resource.mcp');
    Route::get('.well-known/oauth-authorization-server', AuthorizationServerMetadataController::class)->name('oauth.authorization-server');
    Route::get('.well-known/openid-configuration', AuthorizationServerMetadataController::class)->name('oauth.openid-configuration');

    Route::post('oauth/register', [ClientRegistrationController::class, 'store'])->name('oauth.register');
    Route::post('oauth/token', [TokenController::class, 'store'])->name('oauth.token');
    Route::post('oauth/revoke', [TokenRevocationController::class, 'store'])->name('oauth.revoke');

    foreach ([
        $resourceMetadata,
        $mcpResourceMetadata,
        '.well-known/oauth-authorization-server',
        '.well-known/openid-configuration',
        'oauth/register',
        'oauth/token',
        'oauth/revoke',
    ] as $path) {
        Route::options($path, PreflightController::class);
    }
});
