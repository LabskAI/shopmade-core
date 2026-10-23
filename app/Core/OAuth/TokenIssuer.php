<?php

namespace App\Core\OAuth;

use App\Core\Agents\Actor;
use App\Models\ConnectorGrant;
use App\Models\OAuthAccessToken;
use App\Models\OAuthAuthCode;
use App\Models\OAuthClient;
use App\Models\OAuthRefreshToken;
use Illuminate\Support\Facades\DB;

/**
 * The token endpoint's grants: authorization_code (PKCE S256, single use; a replayed code revokes the
 * tokens of its grant) and refresh_token (rotation; a replayed or revoked refresh token revokes the grant).
 */
class TokenIssuer
{
    public function __construct(private ConnectorGrants $grants) {}

    /**
     * Public clients identify themselves with client_id only.
     *
     * @throws OAuthException
     */
    public function client(mixed $clientId): OAuthClient
    {
        if (! is_string($clientId) || $clientId === '') {
            throw OAuthException::invalidClient('client_id is required.');
        }

        $client = strlen($clientId) <= 64 ? OAuthClient::query()->find($clientId) : null;

        if ($client === null || $client->revoked_at !== null) {
            throw OAuthException::invalidClient('Unknown or revoked client. Register the client again.');
        }

        return $client;
    }

    /**
     * @param  array<array-key, mixed>  $input
     * @return array{access_token: string, token_type: string, expires_in: int, refresh_token?: string, scope: string}
     *
     * @throws OAuthException
     */
    public function exchangeAuthorizationCode(OAuthClient $client, array $input): array
    {
        $code = $this->required($input, 'code');
        $redirectUri = $this->required($input, 'redirect_uri');
        $verifier = $this->required($input, 'code_verifier');

        $authCode = OAuthAuthCode::query()->with('user')->find(OpaqueToken::hash($code));

        if ($authCode === null || $authCode->client_id !== $client->id || $authCode->user === null || $authCode->user->isDeactivated()) {
            throw OAuthException::invalidGrant('The authorization code is invalid.');
        }

        if ($authCode->consumed_at !== null) {
            $this->revokeAfterCodeReuse($authCode);

            throw OAuthException::invalidGrant('The authorization code was already used; tokens issued with it were revoked.');
        }

        if (! $authCode->expires_at->isFuture()) {
            throw OAuthException::invalidGrant('The authorization code expired. Start the authorization again.');
        }

        if (! hash_equals($authCode->redirect_uri, $redirectUri)) {
            throw OAuthException::invalidGrant('redirect_uri does not match the authorization request.');
        }

        if (! Pkce::verify($verifier, $authCode->code_challenge)) {
            throw OAuthException::invalidGrant('code_verifier does not match the code challenge.');
        }

        $scopes = Scopes::normalize($authCode->scopes ?? []);

        if ($scopes === []) {
            throw OAuthException::invalidGrant('The authorization code carries no scopes.');
        }

        $resource = $this->resource($input, $authCode->resource);

        $tokens = DB::transaction(function () use ($authCode, $client, $scopes, $resource): ?array {
            $consumed = OAuthAuthCode::query()
                ->whereKey($authCode->getKey())
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            if ($consumed !== 1) {
                return null;
            }

            return $this->issue($this->grants->establish($authCode->user, $client, $scopes), $client, $scopes, $resource);
        });

        if ($tokens === null) {
            $this->revokeAfterCodeReuse($authCode);

            throw OAuthException::invalidGrant('The authorization code was already used; tokens issued with it were revoked.');
        }

        return $tokens;
    }

    /**
     * @param  array<array-key, mixed>  $input
     * @return array{access_token: string, token_type: string, expires_in: int, refresh_token?: string, scope: string}
     *
     * @throws OAuthException
     */
    public function refresh(OAuthClient $client, array $input): array
    {
        if (! in_array('refresh_token', $client->grant_types ?? [], true)) {
            throw OAuthException::unauthorizedClient('This client is not registered for the refresh_token grant.');
        }

        $plaintext = $this->required($input, 'refresh_token');

        $refreshToken = OAuthRefreshToken::query()
            ->with(['grant.user', 'accessToken'])
            ->where('token_hash', OpaqueToken::hash($plaintext))
            ->first();

        if ($refreshToken === null || $refreshToken->client_id !== $client->id) {
            throw OAuthException::invalidGrant('The refresh token is invalid.');
        }

        $grant = $refreshToken->grant;

        if ($refreshToken->used_at !== null || $refreshToken->revoked_at !== null) {
            $this->revokeAfterRefreshReuse($grant);

            throw OAuthException::invalidGrant('The refresh token was already used or revoked; the connection was revoked. Connect again.');
        }

        if (! $refreshToken->expires_at->isFuture()) {
            throw OAuthException::invalidGrant('The refresh token expired. Connect again.');
        }

        if ($grant === null || ! $grant->isActive() || $grant->user === null || $grant->user->isDeactivated()) {
            throw OAuthException::invalidGrant('The connection was revoked. Connect again.');
        }

        $scopes = $this->refreshScopes($input, Scopes::normalize(array_intersect($refreshToken->scopes ?? [], $grant->scopes ?? [])));
        $resource = $this->resource($input, $refreshToken->accessToken?->resource);

        $tokens = DB::transaction(function () use ($refreshToken, $grant, $client, $scopes, $resource): ?array {
            $rotated = OAuthRefreshToken::query()
                ->whereKey($refreshToken->getKey())
                ->whereNull('used_at')
                ->whereNull('revoked_at')
                ->update(['used_at' => now()]);

            if ($rotated !== 1) {
                return null;
            }

            return $this->issue($grant, $client, $scopes, $resource);
        });

        if ($tokens === null) {
            $this->revokeAfterRefreshReuse($grant);

            throw OAuthException::invalidGrant('The refresh token was already used; the connection was revoked. Connect again.');
        }

        return $tokens;
    }

    /**
     * @param  list<string>  $scopes
     * @return array{access_token: string, token_type: string, expires_in: int, refresh_token?: string, scope: string}
     */
    private function issue(ConnectorGrant $grant, OAuthClient $client, array $scopes, ?string $resource): array
    {
        $accessMinutes = max(1, (int) config('agents.oauth.access_token_ttl_minutes', 60));
        $accessToken = OpaqueToken::generate();

        $access = OAuthAccessToken::query()->create([
            'token_hash' => OpaqueToken::hash($accessToken),
            'grant_id' => $grant->id,
            'client_id' => $client->id,
            'user_id' => $grant->user_id,
            'scopes' => $scopes,
            'resource' => $resource,
            'expires_at' => now()->addMinutes($accessMinutes),
        ]);

        $response = [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $accessMinutes * 60,
        ];

        if (in_array('refresh_token', $client->grant_types ?? [], true)) {
            $refreshToken = OpaqueToken::generate();

            OAuthRefreshToken::query()->create([
                'token_hash' => OpaqueToken::hash($refreshToken),
                'grant_id' => $grant->id,
                'access_token_id' => $access->id,
                'client_id' => $client->id,
                'user_id' => $grant->user_id,
                'scopes' => $scopes,
                'expires_at' => now()->addDays(max(1, (int) config('agents.oauth.refresh_token_ttl_days', 30))),
            ]);

            $response['refresh_token'] = $refreshToken;
        }

        return [...$response, 'scope' => implode(' ', $scopes)];
    }

    /**
     * A refresh request may narrow the scope (RFC 6749 §6), never widen it.
     *
     * @param  array<array-key, mixed>  $input
     * @param  list<string>  $available
     * @return list<string>
     */
    private function refreshScopes(array $input, array $available): array
    {
        $requested = $input['scope'] ?? null;

        if ($requested !== null) {
            $parsed = is_string($requested) ? Scopes::parse($requested) : [];

            if ($parsed === [] || array_diff($parsed, $available) !== []) {
                throw OAuthException::invalidScope('The requested scope exceeds what this connection was granted: '.implode(' ', $available).'.');
            }

            $available = Scopes::normalize($parsed);
        }

        if ($available === []) {
            throw OAuthException::invalidGrant('No scopes remain on this connection. Connect again.');
        }

        return $available;
    }

    /**
     * RFC 8707: the token's audience. Only this MCP endpoint is a valid resource.
     *
     * @param  array<array-key, mixed>  $input
     */
    private function resource(array $input, ?string $bound): ?string
    {
        $requested = $input['resource'] ?? null;

        if ($requested === null) {
            return $bound;
        }

        if (! is_string($requested) || ! OAuthUrls::isMcpResource($requested)) {
            throw OAuthException::invalidTarget('resource must be '.OAuthUrls::mcpResource().'.');
        }

        return OAuthUrls::mcpResource();
    }

    /**
     * A replayed code may have been stolen: revoke what was issued for this user and client (OAuth 2.1 §4.1.3).
     */
    private function revokeAfterCodeReuse(OAuthAuthCode $authCode): void
    {
        ConnectorGrant::query()
            ->where('user_id', $authCode->user_id)
            ->where('client_id', $authCode->client_id)
            ->whereNull('revoked_at')
            ->get()
            ->each(fn (ConnectorGrant $grant) => $this->grants->revokeTokens($grant));
    }

    /**
     * A used or revoked refresh token came back: one of the two copies is stolen, so the whole grant goes.
     */
    private function revokeAfterRefreshReuse(?ConnectorGrant $grant): void
    {
        if ($grant !== null) {
            $this->grants->revoke($grant, Actor::system('oauth_refresh_reuse'));
        }
    }

    /**
     * @param  array<array-key, mixed>  $input
     *
     * @throws OAuthException
     */
    private function required(array $input, string $key): string
    {
        $value = $input[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw OAuthException::invalidRequest("{$key} is required.");
        }

        return $value;
    }
}
