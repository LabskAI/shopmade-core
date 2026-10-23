<?php

use App\Core\OAuth\AccessTokens;
use App\Core\OAuth\Pkce;
use App\Models\User;

require_once __DIR__.'/OAuthHelpers.php';

beforeEach(function () {
    oauthUseTestHost();
});

it('connects a claude.ai client end to end and yields a token the MCP endpoint accepts', function () {
    $user = User::factory()->superAdmin()->create();
    $verifier = str_repeat('v', 64);
    $clientId = $this->postJson('/oauth/register', [
        'client_name' => 'Claude',
        'redirect_uris' => [OAUTH_TEST_CALLBACK],
        'token_endpoint_auth_method' => 'none',
    ])->json('client_id');
    $authorization = [
        'response_type' => 'code',
        'client_id' => $clientId,
        'redirect_uri' => OAUTH_TEST_CALLBACK,
        'state' => 'xyz',
        'scope' => 'director operator',
        'code_challenge' => Pkce::challenge($verifier),
        'code_challenge_method' => 'S256',
        'resource' => OAUTH_TEST_MCP_URL,
    ];
    $this->actingAs($user)->get(route('oauth.authorize', $authorization))->assertOk();
    $callback = $this->post(route('oauth.authorize.store'), [...$authorization, 'decision' => 'approve'])->headers->get('Location');

    $tokens = $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $clientId,
        'code' => oauthRedirectQuery($callback)['code'],
        'redirect_uri' => OAUTH_TEST_CALLBACK,
        'code_verifier' => $verifier,
        'resource' => OAUTH_TEST_MCP_URL,
    ])->assertOk();

    $accessTokens = app(AccessTokens::class);
    $token = $accessTokens->resolve($tokens->json('access_token'));
    expect($tokens->json('scope'))->toBe('director operator')
        ->and($token?->user->is($user))->toBeTrue()
        ->and($token->client_id)->toBe($clientId)
        ->and($accessTokens->effectiveScopes($token))->toBe(['director', 'operator']);
});
