<?php

use App\Core\Mcp\Http\McpCorsHeaders;
use App\Http\Controllers\Mcp\McpController;
use App\Http\Middleware\AuthenticateConnector;
use Illuminate\Support\Facades\Route;

/*
| Stateless MCP endpoint (no session, cookies or CSRF): registered from bootstrap/app.php `then:`.
| See docs/front/README.md (Website editor and MCP).
*/

Route::middleware(McpCorsHeaders::class)->group(function (): void {
    $path = (string) config('agents.mcp.path', 'mcp/agents');

    Route::options($path, [McpController::class, 'preflight'])->name('mcp.preflight');
    Route::get($path, [McpController::class, 'stream'])->name('mcp.stream');

    Route::middleware(AuthenticateConnector::class)->group(function () use ($path): void {
        Route::post($path, [McpController::class, 'handle'])->name('mcp.handle');
        Route::delete($path, [McpController::class, 'destroy'])->name('mcp.destroy');
    });
});
