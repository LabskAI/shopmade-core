<?php

namespace App\Core\OAuth;

use App\Models\OAuthAccessToken;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resource-server side of the connector tokens, used by the MCP endpoint on every request:
 * resolve() the Bearer value, read effectiveScopes(), then touch().
 */
class AccessTokens
{
    private const int TOUCH_INTERVAL_SECONDS = 60;

    /**
     * Eager-loads grant and user; null when unknown, expired, revoked or the grant is revoked.
     *
     * Also null when the OAuth client is revoked, the user is gone, or the token was issued for a
     * resource other than this MCP endpoint (RFC 8707 audience).
     */
    public function resolve(string $bearer): ?OAuthAccessToken
    {
        $bearer = trim($bearer);

        if ($bearer === '' || strlen($bearer) > 512) {
            return null;
        }

        $token = OAuthAccessToken::query()
            ->with(['grant', 'user'])
            ->where('token_hash', OpaqueToken::hash($bearer))
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->whereHas('client', fn (Builder $client): Builder => $client->whereNull('revoked_at'))
            ->first();

        if ($token === null || $token->user === null || $token->user->isDeactivated() || $token->grant === null || ! $token->grant->isActive()) {
            return null;
        }

        if ((int) $token->grant->user_id !== (int) $token->user_id || (string) $token->grant->client_id !== (string) $token->client_id) {
            return null;
        }

        if ($token->resource !== null && ! OAuthUrls::isMcpResource($token->resource)) {
            return null;
        }

        return $token;
    }

    /**
     * What a resolved token may do right now: its own scopes, narrowed by its grant (dashboard changes)
     * and by what the user's current role allows (Scopes::allowedFor). Canonical order.
     *
     * @return list<string>
     */
    public function effectiveScopes(OAuthAccessToken $token): array
    {
        $user = $token->user;
        $grant = $token->grant;

        if ($user === null || $grant === null || ! $grant->isActive()) {
            return [];
        }

        return array_values(array_intersect(Scopes::allowedFor($user), $token->scopes ?? [], $grant->scopes ?? []));
    }

    /**
     * Updates last_used_at and the grant's last_seen_at (throttled).
     *
     * Each is written at most once a minute.
     */
    public function touch(OAuthAccessToken $token): void
    {
        $now = now();
        $threshold = $now->copy()->subSeconds(self::TOUCH_INTERVAL_SECONDS);

        if ($token->last_used_at === null || $token->last_used_at->lessThanOrEqualTo($threshold)) {
            $token->forceFill(['last_used_at' => $now])->save();
        }

        $grant = $token->grant;

        if ($grant !== null && ($grant->last_seen_at === null || $grant->last_seen_at->lessThanOrEqualTo($threshold))) {
            $grant->forceFill(['last_seen_at' => $now])->save();
        }
    }
}
