<?php

namespace App\Core\OAuth;

use App\Core\Agents\Actor;
use App\Models\OAuthAccessToken;
use App\Models\OAuthRefreshToken;

/**
 * Token revocation (RFC 7009). Unknown tokens and tokens of another client are ignored silently, so the
 * endpoint never tells a caller whether a token exists.
 */
class TokenRevoker
{
    public function __construct(private ConnectorGrants $grants) {}

    /**
     * An access token is revoked on its own. A refresh token ends the whole connection: its grant and
     * every token of that grant are revoked (RFC 7009 §2.1).
     */
    public function revoke(string $token, ?string $tokenTypeHint, ?string $clientId): void
    {
        $hash = OpaqueToken::hash($token);
        $lookups = $tokenTypeHint === 'refresh_token' ? ['refresh', 'access'] : ['access', 'refresh'];

        foreach ($lookups as $lookup) {
            $found = $lookup === 'access'
                ? $this->revokeAccessToken($hash, $clientId)
                : $this->revokeRefreshToken($hash, $clientId);

            if ($found) {
                return;
            }
        }
    }

    private function revokeAccessToken(string $hash, ?string $clientId): bool
    {
        $token = OAuthAccessToken::query()->where('token_hash', $hash)->first();

        if ($token === null) {
            return false;
        }

        if ($this->belongsTo($token->client_id, $clientId) && $token->revoked_at === null) {
            $token->update(['revoked_at' => now()]);
        }

        return true;
    }

    private function revokeRefreshToken(string $hash, ?string $clientId): bool
    {
        $token = OAuthRefreshToken::query()->with('grant')->where('token_hash', $hash)->first();

        if ($token === null) {
            return false;
        }

        if ($this->belongsTo($token->client_id, $clientId)) {
            if ($token->grant !== null) {
                $this->grants->revoke($token->grant, Actor::system('oauth_revocation'));
            } elseif ($token->revoked_at === null) {
                $token->update(['revoked_at' => now()]);
            }
        }

        return true;
    }

    private function belongsTo(string $tokenClientId, ?string $clientId): bool
    {
        return $clientId === null || hash_equals($tokenClientId, $clientId);
    }
}
