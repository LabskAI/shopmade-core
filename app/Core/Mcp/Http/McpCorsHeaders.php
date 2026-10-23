<?php

namespace App\Core\Mcp\Http;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CORS for the MCP endpoint (ARCHITECTURE §0 #11): browser clients such as the MCP Inspector must be able to
 * read every answer, including the 401 challenge. Authentication is a bearer token only (no cookies), so any
 * origin is allowed.
 */
class McpCorsHeaders
{
    /**
     * @var array<string, string>
     */
    public const Headers = [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, DELETE, OPTIONS',
        'Access-Control-Allow-Headers' => 'Authorization, Content-Type, Mcp-Session-Id, MCP-Protocol-Version, Last-Event-ID',
        'Access-Control-Expose-Headers' => 'Mcp-Session-Id, WWW-Authenticate',
        'Access-Control-Max-Age' => '86400',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::Headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }
}
