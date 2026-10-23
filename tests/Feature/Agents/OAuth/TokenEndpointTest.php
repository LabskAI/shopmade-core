<?php

use App\Models\ConnectorGrant;
use App\Models\OAuthAccessToken;
use App\Models\OAuthAuthCode;
use App\Models\OAuthClient;
use App\Models\OAuthRefreshToken;
use App\Models\User;

require_once __DIR__.'/OAuthHelpers.php';

beforeEach(function () {
    oauthUseTestHost();
});

it('exchanges a code and its PKCE verifier for tokens bound to a grant', function () {
    [$code, $plaintext] = oauthCreateCode(['scopes' => ['director', 'operator'], 'resource' => OAUTH_TEST_MCP_URL]);

    $response = $this->post('/oauth/token', oauthCodeExchange($code, $plaintext, ['resource' => OAUTH_TEST_MCP_URL]));

    $response->assertOk()
        ->assertJson(['token_type' => 'Bearer', 'expires_in' => 3600, 'scope' => 'director operator']);
    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->json('access_token'))->toBeString()->not->toBeEmpty()
        ->and($response->json('refresh_token'))->toBeString()->not->toBe($response->json('access_token'));
    $grant = ConnectorGrant::query()->sole();
    expect($grant->user_id)->toBe($code->user_id)
        ->and($grant->client_id)->toBe($code->client_id)
        ->and($grant->scopes)->toBe(['director', 'operator']);
    $access = OAuthAccessToken::query()->where('token_hash', hash('sha256', $response->json('access_token')))->sole();
    expect($access->grant_id)->toBe($grant->id)
        ->and($access->scopes)->toBe(['director', 'operator'])
        ->and($access->resource)->toBe(OAUTH_TEST_MCP_URL);
    $this->assertDatabaseHas('oauth_refresh_tokens', [
        'token_hash' => hash('sha256', $response->json('refresh_token')),
        'grant_id' => $grant->id,
        'access_token_id' => $access->id,
    ]);
    expect($code->fresh()->consumed_at)->not->toBeNull();
});

it('accepts a JSON token request', function () {
    [$code, $plaintext] = oauthCreateCode();

    $this->postJson('/oauth/token', oauthCodeExchange($code, $plaintext))
        ->assertOk()
        ->assertJsonPath('scope', 'director');
});

it('refuses a code exchange that does not match the authorization', function (array $codeAttributes, array $overrides) {
    [$code, $plaintext] = oauthCreateCode($codeAttributes);

    $this->post('/oauth/token', oauthCodeExchange($code, $plaintext, $overrides))
        ->assertBadRequest()
        ->assertJsonPath('error', 'invalid_grant');

    $this->assertDatabaseCount('oauth_access_tokens', 0);
    expect($code->fresh()->consumed_at)->toBeNull();
})->with([
    'wrong verifier' => [fn () => [], fn () => ['code_verifier' => 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXl']],
    'expired code' => [fn () => ['expires_at' => now()->subSecond()], fn () => []],
    'other redirect URI' => [fn () => [], fn () => ['redirect_uri' => 'https://claude.com/api/mcp/auth_callback']],
    'code of another client' => [fn () => [], fn () => ['client_id' => OAuthClient::factory()->create()->id]],
    'unknown code' => [fn () => [], fn () => ['code' => 'not-a-code']],
]);

it('refuses a replayed code and revokes the tokens issued with it', function () {
    [$code, $plaintext] = oauthCreateCode();
    $first = $this->post('/oauth/token', oauthCodeExchange($code, $plaintext))->assertOk();

    $this->post('/oauth/token', oauthCodeExchange($code, $plaintext))
        ->assertBadRequest()
        ->assertJsonPath('error', 'invalid_grant');

    expect(OAuthAccessToken::query()->where('token_hash', hash('sha256', $first->json('access_token')))->sole()->revoked_at)->not->toBeNull()
        ->and(OAuthRefreshToken::query()->where('token_hash', hash('sha256', $first->json('refresh_token')))->sole()->revoked_at)->not->toBeNull()
        ->and(OAuthAccessToken::query()->count())->toBe(1);
});

it('keeps one grant per user and client and narrows it to the latest consent', function () {
    $user = User::factory()->superAdmin()->create();
    $client = OAuthClient::factory()->create();
    [$wide, $widePlaintext] = oauthCreateCode(['user_id' => $user->id, 'client_id' => $client->id, 'scopes' => ['director', 'operator']]);
    [$narrow, $narrowPlaintext] = oauthCreateCode(['user_id' => $user->id, 'client_id' => $client->id, 'scopes' => ['director']]);
    $first = $this->post('/oauth/token', oauthCodeExchange($wide, $widePlaintext))->assertOk();

    $this->post('/oauth/token', oauthCodeExchange($narrow, $narrowPlaintext))->assertOk();

    expect(ConnectorGrant::query()->sole()->scopes)->toBe(['director'])
        ->and(OAuthAccessToken::query()->where('token_hash', hash('sha256', $first->json('access_token')))->sole()->scopes)->toBe(['director']);
});

it('rotates the refresh token', function () {
    [$code, $plaintext] = oauthCreateCode();
    $first = $this->post('/oauth/token', oauthCodeExchange($code, $plaintext))->json();

    $response = $this->post('/oauth/token', [
        'grant_type' => 'refresh_token',
        'client_id' => $code->client_id,
        'refresh_token' => $first['refresh_token'],
    ]);

    $response->assertOk()->assertJsonPath('scope', 'director');
    expect($response->json('refresh_token'))->not->toBe($first['refresh_token'])
        ->and($response->json('access_token'))->not->toBe($first['access_token'])
        ->and(OAuthRefreshToken::query()->where('token_hash', hash('sha256', $first['refresh_token']))->sole()->used_at)->not->toBeNull();
    $this->assertDatabaseHas('oauth_refresh_tokens', [
        'token_hash' => hash('sha256', $response->json('refresh_token')),
        'used_at' => null,
        'revoked_at' => null,
    ]);
});

it('revokes the whole grant when a used refresh token is replayed', function () {
    [$code, $plaintext] = oauthCreateCode();
    $first = $this->post('/oauth/token', oauthCodeExchange($code, $plaintext))->json();
    $refresh = ['grant_type' => 'refresh_token', 'client_id' => $code->client_id, 'refresh_token' => $first['refresh_token']];
    $this->post('/oauth/token', $refresh)->assertOk();

    $this->post('/oauth/token', $refresh)
        ->assertBadRequest()
        ->assertJsonPath('error', 'invalid_grant');

    expect(ConnectorGrant::query()->sole()->revoked_at)->not->toBeNull()
        ->and(OAuthAccessToken::query()->whereNull('revoked_at')->count())->toBe(0)
        ->and(OAuthRefreshToken::query()->whereNull('revoked_at')->count())->toBe(0);
});

it('refuses a refresh token presented by another client without revoking the grant', function () {
    [$code, $plaintext] = oauthCreateCode();
    $first = $this->post('/oauth/token', oauthCodeExchange($code, $plaintext))->json();

    $this->post('/oauth/token', [
        'grant_type' => 'refresh_token',
        'client_id' => OAuthClient::factory()->create()->id,
        'refresh_token' => $first['refresh_token'],
    ])
        ->assertBadRequest()
        ->assertJsonPath('error', 'invalid_grant');

    expect(ConnectorGrant::query()->sole()->revoked_at)->toBeNull();
});

it('narrows the scope on refresh but never widens it', function (array $granted, string $scope, int $status, string $field, string $expected) {
    [$code, $plaintext] = oauthCreateCode(['scopes' => $granted]);
    $first = $this->post('/oauth/token', oauthCodeExchange($code, $plaintext))->json();

    $this->post('/oauth/token', [
        'grant_type' => 'refresh_token',
        'client_id' => $code->client_id,
        'refresh_token' => $first['refresh_token'],
        'scope' => $scope,
    ])
        ->assertStatus($status)
        ->assertJsonPath($field, $expected);
})->with([
    'narrower' => [['director', 'operator'], 'director', 200, 'scope', 'director'],
    'wider' => [['operator'], 'director operator', 400, 'error', 'invalid_scope'],
]);

it('answers malformed token requests with RFC 6749 errors', function (Closure $request, int $status, string $error) {
    $response = $this->post('/oauth/token', $request());

    $response->assertStatus($status)->assertJsonPath('error', $error);
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    $this->assertDatabaseCount('oauth_access_tokens', 0);
})->with([
    'unknown client' => [function () {
        [$code, $plaintext] = oauthCreateCode();

        return oauthCodeExchange($code, $plaintext, ['client_id' => 'smc_unknown']);
    }, 401, 'invalid_client'],
    'unsupported grant type' => [fn () => ['grant_type' => 'password', 'client_id' => OAuthClient::factory()->create()->id], 400, 'unsupported_grant_type'],
    'missing verifier' => [function () {
        [$code, $plaintext] = oauthCreateCode();

        return oauthCodeExchange($code, $plaintext, ['code_verifier' => '']);
    }, 400, 'invalid_request'],
    'another resource' => [function () {
        [$code, $plaintext] = oauthCreateCode();

        return oauthCodeExchange($code, $plaintext, ['resource' => 'https://evil.example/mcp/agents']);
    }, 400, 'invalid_target'],
    'refresh for a client without the refresh grant' => [function () {
        $client = OAuthClient::factory()->create(['grant_types' => ['authorization_code']]);

        return ['grant_type' => 'refresh_token', 'client_id' => $client->id, 'refresh_token' => 'anything'];
    }, 400, 'unauthorized_client'],
]);

it('issues no refresh token to a client registered without the refresh grant', function () {
    $client = OAuthClient::factory()->create(['grant_types' => ['authorization_code']]);
    [$code, $plaintext] = oauthCreateCode(['client_id' => $client->id]);

    $this->post('/oauth/token', oauthCodeExchange($code, $plaintext))
        ->assertOk()
        ->assertJsonMissingPath('refresh_token');

    $this->assertDatabaseCount('oauth_refresh_tokens', 0);
    expect(OAuthAuthCode::query()->sole()->consumed_at)->not->toBeNull();
});
