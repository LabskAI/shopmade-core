<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanManageCoreTool
{
    public function handle(Request $request, Closure $next, string $slug): Response
    {
        if (! $request->user()?->canManageCoreTool($slug)) {
            abort(403);
        }

        return $next($request);
    }
}
