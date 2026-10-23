<?php

namespace App\Http\Controllers\OAuth;

use App\Core\OAuth\OAuthUrls;
use App\Core\OAuth\Scopes;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ProtectedResourceMetadataController extends Controller
{
    /**
     * RFC 9728 metadata for the MCP endpoint, served at the well-known root and at the MCP path suffix.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'resource' => OAuthUrls::mcpResource(),
            'authorization_servers' => [OAuthUrls::issuer()],
            'scopes_supported' => Scopes::all(),
            'bearer_methods_supported' => ['header'],
            'resource_name' => config('app.name').' agents',
        ], options: JSON_UNESCAPED_SLASHES);
    }
}
