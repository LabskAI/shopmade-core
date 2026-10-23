<?php

use App\Models\OAuthAuthCode;
use App\Models\OAuthClient;
use App\Models\User;

require_once __DIR__.'/OAuthHelpers.php';

beforeEach(function () {
    oauthUseTestHost();
});

it('sends guests to the login page and back to the authorization request after signing in', function () {
    $client = OAuthClient::factory()->create();
    $user = User::factory()->superAdmin()->create();
    $url = route('oauth.authorize', oauthAuthorizeQuery($client));

    $this->get($url)->assertRedirect(route('login'));

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])->assertRedirect($url);
});

it('shows an error page and never redirects while the client or its redirect URI is untrusted', function (Closure $request, string $message) {
    config(['agents.oauth.allow_loopback_redirects' => true]);
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('oauth.authorize', $request()));

    $response->assertBadRequest()->assertSee($message)->assertHeaderMissing('Location');
    $this->assertDatabaseCount('oauth_auth_codes', 0);
})->with([
    'unknown client' => [
        fn () => oauthAuthorizeQuery(OAuthClient::factory()->make(['id' => 'smc_unknown'])),
        'This app is not registered here',
    ],
    'revoked client' => [
        fn () => oauthAuthorizeQuery(OAuthClient::factory()->create(['revoked_at' => now()])),
        'This app is not registered here',
    ],
    'redirect URI not registered for the client' => [
        fn () => oauthAuthorizeQuery(OAuthClient::factory()->create(), ['redirect_uri' => 'https://claude.com/api/mcp/auth_callback']),
        'The return address in this request is not registered',
    ],
    'loopback redirect switched off since registration' => [
        function () {
            $client = OAuthClient::factory()->create(['redirect_uris' => ['http://localhost:6274/oauth/callback']]);
            config(['agents.oauth.allow_loopback_redirects' => false]);

            return oauthAuthorizeQuery($client, ['redirect_uri' => 'http://localhost:6274/oauth/callback']);
        },
        'The return address in this request is not registered',
    ],
    'missing redirect URI' => [
        fn () => oauthAuthorizeQuery(OAuthClient::factory()->create(), ['redirect_uri' => null]),
        'redirect_uri is missing',
    ],
]);

it('sends request errors back to the client with state and issuer', function (array $overrides, string $error) {
    $client = OAuthClient::factory()->create();

    $response = $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('oauth.authorize', oauthAuthorizeQuery($client, $overrides)));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toStartWith(OAUTH_TEST_CALLBACK.'?')
        ->and(oauthRedirectQuery($response->headers->get('Location')))
        ->toMatchArray(['error' => $error, 'state' => 'state-123', 'iss' => 'https://shop.example'])
        ->not->toHaveKey('code');
    $this->assertDatabaseCount('oauth_auth_codes', 0);
})->with([
    'no PKCE challenge' => [['code_challenge' => null], 'invalid_request'],
    'plain PKCE method' => [['code_challenge_method' => 'plain'], 'invalid_request'],
    'no PKCE method' => [['code_challenge_method' => null], 'invalid_request'],
    'challenge too short' => [['code_challenge' => 'too-short'], 'invalid_request'],
    'implicit flow' => [['response_type' => 'token'], 'unsupported_response_type'],
    'unknown scope' => [['scope' => 'director admin'], 'invalid_scope'],
    'another resource' => [['resource' => 'https://evil.example/mcp/agents'], 'invalid_target'],
]);

it('shows the consent screen with director by default to an admin', function () {
    $client = OAuthClient::factory()->create(['name' => 'Claude']);
    $user = User::factory()->superAdmin()->create(['email' => 'ana@shop.example']);

    $response = $this->actingAs($user)->get(route('oauth.authorize', oauthAuthorizeQuery($client)));

    $response->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertSee('has not verified it')
        ->assertSee('claude.ai')
        ->assertSee('ana@shop.example')
        ->assertSee('(director)', false)
        ->assertDontSee('optional_scopes', false);
});

it('denies a staff user who cannot edit the site', function () {
    $client = OAuthClient::factory()->create();

    $response = $this->actingAs(User::factory()->user()->create())
        ->get(route('oauth.authorize', oauthAuthorizeQuery($client)));

    $response->assertForbidden()
        ->assertSee('Access denied')
        ->assertSee(OAUTH_TEST_CALLBACK.'?error=access_denied');
    $this->assertDatabaseCount('oauth_auth_codes', 0);
});

it('escapes the self-declared client name', function () {
    $client = OAuthClient::factory()->create(['name' => '<script>alert(1)</script>']);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('oauth.authorize', oauthAuthorizeQuery($client)))
        ->assertOk()
        ->assertSee('<script>alert(1)</script>')
        ->assertDontSee('<script>alert(1)</script>', false);
});

it('issues a single-use code bound to the request when the user approves', function () {
    $client = OAuthClient::factory()->create();
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->post(route('oauth.authorize.store'), [
        ...oauthAuthorizeQuery($client, ['resource' => OAUTH_TEST_MCP_URL]),
        'decision' => 'approve',
    ]);

    $response->assertRedirect();
    $query = oauthRedirectQuery($response->headers->get('Location'));
    expect($response->headers->get('Location'))->toStartWith(OAUTH_TEST_CALLBACK.'?')
        ->and($query)->toHaveKeys(['code', 'state', 'iss'])
        ->and($query['state'])->toBe('state-123')
        ->and($query['iss'])->toBe('https://shop.example');
    $this->assertDatabaseHas('oauth_auth_codes', [
        'id' => hash('sha256', $query['code']),
        'client_id' => $client->id,
        'user_id' => $user->id,
        'redirect_uri' => OAUTH_TEST_CALLBACK,
        'code_challenge' => OAUTH_TEST_CHALLENGE,
        'code_challenge_method' => 'S256',
        'resource' => OAUTH_TEST_MCP_URL,
        'consumed_at' => null,
    ]);
    expect(OAuthAuthCode::query()->sole()->scopes)->toBe(['director']);
});

it('returns access_denied to the client when the user denies', function () {
    $client = OAuthClient::factory()->create();

    $response = $this->actingAs(User::factory()->superAdmin()->create())->post(route('oauth.authorize.store'), [
        ...oauthAuthorizeQuery($client),
        'decision' => 'deny',
    ]);

    $response->assertRedirect();
    expect(oauthRedirectQuery($response->headers->get('Location')))
        ->toMatchArray(['error' => 'access_denied', 'state' => 'state-123', 'iss' => 'https://shop.example'])
        ->not->toHaveKey('code');
    $this->assertDatabaseCount('oauth_auth_codes', 0);
});
