<?php

namespace App\Core\Mcp;

use Illuminate\Support\Facades\RateLimiter;

/**
 * The per-access-token request budget of the MCP server (agents.mcp.rate_limit_per_minute). Tool calls,
 * resources, prompts and the list methods share one budget; initialize, ping and notifications never count.
 */
class McpRateLimit
{
    private const WindowSeconds = 60;

    /**
     * Counts one request for the caller's access token.
     *
     * @throws McpError when the token has used its budget for this minute (-32003 with data.retry_after)
     */
    public function hit(McpContext $context): void
    {
        $key = 'mcp-requests:'.$context->token->getKey();
        $perMinute = max(1, (int) config('agents.mcp.rate_limit_per_minute', 60));

        if (RateLimiter::tooManyAttempts($key, $perMinute)) {
            throw McpError::rateLimited(max(1, RateLimiter::availableIn($key)), $perMinute);
        }

        RateLimiter::hit($key, self::WindowSeconds);
    }
}
