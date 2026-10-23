<?php

use App\Core\OAuth\RedirectUriPolicy;
use App\Models\OAuthClient;

require_once __DIR__.'/OAuthHelpers.php';

beforeEach(function () {
    oauthUseTestHost();
    config(['agents.oauth.allow_loopback_redirects' => true]);
});

it('registers a public client for the claude.ai callback whatever auth method it asks for', function () {
    $response = $this->postJson('/oauth/register', [
        'client_name' => 'Claude',
        'redirect_uris' => [OAUTH_TEST_CALLBACK],
        'grant_types' => ['authorization_code', 'refresh_token'],
        'response_types' => ['code'],
        'token_endpoint_auth_method' => 'client_secret_post',
        'scope' => 'director operator',
    ]);

    $response->assertCreated()
        ->assertJson([
            'client_name' => 'Claude',
            'redirect_uris' => [OAUTH_TEST_CALLBACK],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
            'scope' => 'director operator',
        ])
        ->assertJsonMissingPath('client_secret');
    expect($response->json('client_id'))->toStartWith('smc_')
        ->and($response->json('client_id_issued_at'))->toBeInt()
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
    $this->assertDatabaseHas('oauth_clients', [
        'id' => $response->json('client_id'),
        'name' => 'Claude',
        'token_endpoint_auth_method' => 'none',
    ]);
});

it('registers loopback callbacks such as the MCP Inspector while loopback redirects are allowed', function () {
    $uris = ['http://localhost:6274/oauth/callback', 'http://127.0.0.1:6274/oauth/callback/debug'];

    $this->postJson('/oauth/register', ['client_name' => 'MCP Inspector', 'redirect_uris' => $uris])
        ->assertCreated()
        ->assertJsonPath('redirect_uris', $uris)
        ->assertJsonPath('grant_types', ['authorization_code', 'refresh_token']);
});

it('rejects redirect URIs outside the allowlist', function (string $uri) {
    $this->postJson('/oauth/register', ['redirect_uris' => [$uri]])
        ->assertBadRequest()
        ->assertJsonPath('error', 'invalid_redirect_uri');

    $this->assertDatabaseCount('oauth_clients', 0);
})->with([
    'another host' => 'https://evil.example/api/mcp/auth_callback',
    'https lookalike host' => 'https://claude.ai.evil.example/api/mcp/auth_callback',
    'plain http' => 'http://claude.ai/api/mcp/auth_callback',
    'other path' => 'https://claude.ai/api/mcp/auth_callback/extra',
    'fragment' => 'https://claude.ai/api/mcp/auth_callback#done',
    'loopback over https' => 'https://localhost:6274/oauth/callback',
    'loopback without a port' => 'http://localhost/oauth/callback',
    'loopback with user info' => 'http://localhost:6274@evil.example/callback',
    'localhost lookalike' => 'http://localhost.evil.example:6274/callback',
]);

it('rejects loopback redirect URIs when loopback is off, the production default', function () {
    config(['agents.oauth.allow_loopback_redirects' => false]);

    $this->postJson('/oauth/register', ['redirect_uris' => ['http://localhost:6274/oauth/callback']])
        ->assertBadRequest()
        ->assertJsonPath('error', 'invalid_redirect_uri');

    $this->assertDatabaseCount('oauth_clients', 0);
});

it('registers the Cursor connector callback when loopback is off, the production default', function () {
    config(['agents.oauth.allow_loopback_redirects' => false]);

    $uri = 'https://www.cursor.com/agents/mcp/oauth/callback';

    $this->postJson('/oauth/register', [
        'client_name' => 'Cursor',
        'redirect_uris' => [$uri],
        'token_endpoint_auth_method' => 'none',
        'grant_types' => ['authorization_code', 'refresh_token'],
        'response_types' => ['code'],
        'scope' => 'director',
    ])
        ->assertCreated()
        ->assertJsonPath('redirect_uris', [$uri])
        ->assertJsonPath('token_endpoint_auth_method', 'none')
        ->assertJsonPath('scope', 'director');

    $this->assertDatabaseHas('oauth_clients', [
        'name' => 'Cursor',
        'token_endpoint_auth_method' => 'none',
    ]);
});

it('registers extra exact redirect URIs from AGENTS_OAUTH_EXTRA_REDIRECT_URIS', function () {
    $extra = 'https://connectors.example/oauth/callback';
    $csv = "  {$extra}, , {$extra} ";

    putenv("AGENTS_OAUTH_EXTRA_REDIRECT_URIS={$csv}");
    $_ENV['AGENTS_OAUTH_EXTRA_REDIRECT_URIS'] = $csv;
    $_SERVER['AGENTS_OAUTH_EXTRA_REDIRECT_URIS'] = $csv;

    try {
        $agents = require config_path('agents.php');

        config([
            'agents.oauth.allow_loopback_redirects' => false,
            'agents.oauth.extra_redirect_uris' => $agents['oauth']['extra_redirect_uris'],
        ]);

        $this->postJson('/oauth/register', ['client_name' => 'Extra', 'redirect_uris' => [$extra]])
            ->assertCreated()
            ->assertJsonPath('redirect_uris', [$extra]);

        $this->assertDatabaseHas('oauth_clients', ['name' => 'Extra']);
    } finally {
        putenv('AGENTS_OAUTH_EXTRA_REDIRECT_URIS');
        unset($_ENV['AGENTS_OAUTH_EXTRA_REDIRECT_URIS'], $_SERVER['AGENTS_OAUTH_EXTRA_REDIRECT_URIS']);
    }
});

it('parses AGENTS_OAUTH_EXTRA_REDIRECT_URIS as trimmed unique exact URIs', function (string $csv, array $uris) {
    expect(RedirectUriPolicy::extraRedirectUris($csv))->toBe($uris);
})->with([
    'trimmed unique list' => ['  https://a.example/cb, ,https://b.example/cb , https://a.example/cb', ['https://a.example/cb', 'https://b.example/cb']],
    'empty' => ['', []],
    'only separators' => ['   , , ', []],
]);

it('does not treat extra redirect URIs as wildcards', function () {
    config([
        'agents.oauth.allow_loopback_redirects' => false,
        'agents.oauth.extra_redirect_uris' => RedirectUriPolicy::extraRedirectUris('https://*.example/callback'),
    ]);

    $this->postJson('/oauth/register', ['redirect_uris' => ['https://evil.example/callback']])
        ->assertBadRequest()
        ->assertJsonPath('error', 'invalid_redirect_uri');

    $this->assertDatabaseCount('oauth_clients', 0);
});

it('rejects an unlisted https redirect URI when loopback is off', function () {
    config(['agents.oauth.allow_loopback_redirects' => false]);

    $this->postJson('/oauth/register', ['redirect_uris' => ['https://evil.example/api/mcp/auth_callback']])
        ->assertBadRequest()
        ->assertJsonPath('error', 'invalid_redirect_uri');

    $this->assertDatabaseCount('oauth_clients', 0);
});

it('rejects a mixed allowlisted URI and disallowed loopback when loopback is off', function () {
    config(['agents.oauth.allow_loopback_redirects' => false]);

    $this->postJson('/oauth/register', [
        'client_name' => 'Cursor',
        'redirect_uris' => [
            'https://www.cursor.com/agents/mcp/oauth/callback',
            'http://localhost:8787/callback',
        ],
        'token_endpoint_auth_method' => 'none',
        'grant_types' => ['authorization_code', 'refresh_token'],
        'response_types' => ['code'],
        'scope' => 'director',
    ])
        ->assertBadRequest()
        ->assertJsonPath('error', 'invalid_redirect_uri');

    $this->assertDatabaseCount('oauth_clients', 0);
});

it('requires at least one redirect URI', function () {
    $this->postJson('/oauth/register', ['client_name' => 'Claude'])
        ->assertBadRequest()
        ->assertJsonPath('error', 'invalid_redirect_uri');
});

it('rejects grant and response types beyond code and refresh', function (array $metadata) {
    $this->postJson('/oauth/register', ['redirect_uris' => [OAUTH_TEST_CALLBACK], ...$metadata])
        ->assertBadRequest()
        ->assertJsonPath('error', 'invalid_client_metadata');

    $this->assertDatabaseCount('oauth_clients', 0);
})->with([
    'client credentials' => [['grant_types' => ['authorization_code', 'client_credentials']]],
    'refresh without code' => [['grant_types' => ['refresh_token']]],
    'implicit token' => [['response_types' => ['token']]],
]);

it('rejects a body that is not a JSON object', function (string $body) {
    $this->call('POST', '/oauth/register', server: ['CONTENT_TYPE' => 'application/json'], content: $body)
        ->assertBadRequest()
        ->assertJsonPath('error', 'invalid_client_metadata');
})->with([
    'form encoded' => 'redirect_uris=https://claude.ai/api/mcp/auth_callback',
    'JSON list' => '["https://claude.ai/api/mcp/auth_callback"]',
]);

it('sanitizes the self-declared client name', function (string $name, string $stored) {
    $this->postJson('/oauth/register', ['client_name' => $name, 'redirect_uris' => [OAUTH_TEST_CALLBACK]])
        ->assertCreated()
        ->assertJsonPath('client_name', $stored);

    expect(OAuthClient::query()->sole()->name)->toBe($stored);
})->with([
    'control and bidi characters' => ["Clau\u{202E}de\u{0007}  Desktop", 'Clau de Desktop'],
    'longer than 100 characters' => [str_repeat('a', 150), str_repeat('a', 100)],
    'nothing printable' => [" \u{0000} ", 'Unnamed app'],
]);

it('allows 20 registrations per hour from one address', function () {
    $metadata = ['redirect_uris' => [OAUTH_TEST_CALLBACK]];

    foreach (range(1, 20) as $attempt) {
        $this->postJson('/oauth/register', $metadata)->assertCreated();
    }

    $this->postJson('/oauth/register', $metadata)
        ->assertTooManyRequests()
        ->assertHeader('Retry-After')
        ->assertJsonPath('error', 'temporarily_unavailable');
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
        ->postJson('/oauth/register', $metadata)
        ->assertCreated();
    $this->assertDatabaseCount('oauth_clients', 21);
});
