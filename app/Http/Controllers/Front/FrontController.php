<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\Response;

abstract class FrontController extends Controller implements HasMiddleware
{
    /**
     * The bundled fictional "demo" company is never indexed, whatever its pages say (404s included).
     *
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware(static function (Request $request, Closure $next): Response {
                $response = $next($request);

                if (config('agents.company') === 'demo') {
                    $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
                }

                return $response;
            }),
        ];
    }
}
