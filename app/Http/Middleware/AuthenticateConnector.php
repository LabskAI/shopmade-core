<?php

namespace App\Http\Middleware;

use App\Core\Agents\Actor;
use App\Core\Mcp\McpContext;
use App\Core\Mcp\McpError;
use App\Core\OAuth\AccessTokens;
use App\Core\OAuth\OAuthUrls;
use App\Models\OAuthAccessToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer authentication for the MCP endpoint. A valid token becomes an McpContext on the request;
 * anything else is a 401 with the RFC 9728 challenge that starts the client's OAuth discovery.
 */
class AuthenticateConnector
{
    public function __construct(private AccessTokens $tokens) {}

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = trim((string) $request->bearerToken());
        $token = $bearer === '' ? null : $this->tokens->resolve($bearer);

        if ($token === null || ! $this->isUsable($token)) {
            return $this->unauthorized($bearer !== '');
        }

        $request->attributes->set(McpContext::class, new McpContext(
            user: $token->user,
            grant: $token->grant,
            token: $token,
            clientId: (string) $token->client_id,
            scopes: $this->tokens->effectiveScopes($token),
            actor: Actor::connector($token->user, (string) $token->client_id),
        ));

        $this->tokens->touch($token);

        return $next($request);
    }

    /**
     * AccessTokens::resolve() already refuses expired and revoked tokens; this re-checks the parts the
     * MCP surface depends on, including that the token was issued for this resource.
     */
    private function isUsable(OAuthAccessToken $token): bool
    {
        $grant = $token->grant;

        if ($token->user === null || $grant === null || ! $grant->isActive() || $token->revoked_at !== null) {
            return false;
        }

        if ($token->expires_at !== null && $token->expires_at->isPast()) {
            return false;
        }

        return blank($token->resource) || OAuthUrls::isMcpResource((string) $token->resource);
    }

    private function unauthorized(bool $tokenPresented): Response
    {
        $challenge = 'Bearer resource_metadata="'.OAuthUrls::resourceMetadata().'", scope="director"';

        if ($tokenPresented) {
            $challenge .= ', error="invalid_token", error_description="The access token is invalid, expired or revoked"';
        }

        $error = McpError::unauthorized(
            $tokenPresented ? 'Unauthorized: the access token is invalid, expired or revoked.' : 'Unauthorized: a Bearer access token is required.',
            'Reconnect the connector to sign in again (OAuth metadata: '.OAuthUrls::resourceMetadata().').',
        );

        return response()->json(
            ['jsonrpc' => '2.0', 'id' => null, 'error' => $error->toError()],
            401,
            ['WWW-Authenticate' => $challenge],
            JSON_UNESCAPED_SLASHES,
        );
    }
}
