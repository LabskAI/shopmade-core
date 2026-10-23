<?php

namespace App\Core\OAuth;

use App\Models\OAuthAuthCode;
use App\Models\User;

class AuthorizationCodes
{
    /**
     * Issues a single-use code bound to the client, redirect URI, PKCE challenge, resource, user and the
     * approved scopes. Only its SHA-256 is stored (as the row id).
     *
     * @param  list<string>  $scopes
     */
    public function issue(AuthorizationRequest $request, User $user, array $scopes): string
    {
        $code = OpaqueToken::generate();

        OAuthAuthCode::query()->create([
            'id' => OpaqueToken::hash($code),
            'client_id' => $request->client->id,
            'user_id' => $user->id,
            'scopes' => $scopes,
            'redirect_uri' => $request->redirectUri,
            'code_challenge' => $request->codeChallenge,
            'code_challenge_method' => 'S256',
            'resource' => $request->resource,
            'expires_at' => now()->addMinutes(max(1, (int) config('agents.oauth.auth_code_ttl_minutes', 10))),
        ]);

        return $code;
    }
}
