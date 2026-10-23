<?php

use App\Http\Middleware\EnsureCanManageCoreTool;
use App\Http\Middleware\PluginActive;
use App\Http\Middleware\SignOutDeactivatedUser;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [
            __DIR__.'/../routes/front.php',
            __DIR__.'/../routes/web.php',
            __DIR__.'/../routes/site.php',
            __DIR__.'/../routes/oauth.php',
        ],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::group([], __DIR__.'/../routes/oauth-api.php');
            Route::group([], __DIR__.'/../routes/mcp.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [AuthenticateSession::class, SignOutDeactivatedUser::class]);
        $middleware->alias([
            'plugin' => PluginActive::class,
            'can-manage-core' => EnsureCanManageCoreTool::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (InvalidSignatureException $e, Request $request) {
            if ($request->is('portal/password/*')) {
                return response()->view('auth.portal-invite-invalid', [
                    'message' => 'This invite link is invalid or has expired.',
                ], 403);
            }
        });
    })->create();
