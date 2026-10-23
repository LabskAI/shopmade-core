<?php

namespace App\Core\OAuth;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CORS for the browser-facing OAuth endpoints (/.well-known/*, register, token, revoke): any origin, no
 * credentials, so browser clients such as the MCP Inspector can run discovery and the token exchange.
 */
class CrossOriginHeaders
{
    /**
     * @return array<string, string>
     */
    public static function headers(): array
    {
        return [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Authorization, Content-Type, Mcp-Session-Id, MCP-Protocol-Version, Last-Event-ID',
            'Access-Control-Expose-Headers' => 'Mcp-Session-Id, WWW-Authenticate',
            'Access-Control-Max-Age' => '86400',
        ];
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->add(self::headers());

        return $response;
    }
}
