<?php

use App\Core\OAuth\OAuthUrls;

require_once __DIR__.'/OAuthHelpers.php';

beforeEach(function () {
    oauthUseTestHost();
});

it('describes the MCP endpoint as a protected resource at both well-known locations', function (string $path) {
    $this->getJson($path)
        ->assertOk()
        ->assertExactJson([
            'resource' => 'https://shop.example/mcp/agents',
            'authorization_servers' => ['https://shop.example'],
            'scopes_supported' => ['director', 'operator'],
            'bearer_methods_supported' => ['header'],
            'resource_name' => config('app.name').' agents',
        ]);
})->with([
    'root' => '/.well-known/oauth-protected-resource',
    'MCP path suffix' => '/.well-known/oauth-protected-resource/mcp/agents',
]);

it('publishes the authorization server metadata for OAuth and OpenID discovery', function (string $path) {
    $this->getJson($path)
        ->assertOk()
        ->assertExactJson([
            'issuer' => 'https://shop.example',
            'authorization_endpoint' => 'https://shop.example/oauth/authorize',
            'token_endpoint' => 'https://shop.example/oauth/token',
            'registration_endpoint' => 'https://shop.example/oauth/register',
            'revocation_endpoint' => 'https://shop.example/oauth/revoke',
            'response_types_supported' => ['code'],
            'response_modes_supported' => ['query'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'revocation_endpoint_auth_methods_supported' => ['none'],
            'scopes_supported' => ['director', 'operator'],
            'authorization_response_iss_parameter_supported' => true,
        ]);
})->with([
    'RFC 8414' => '/.well-known/oauth-authorization-server',
    'OpenID configuration' => '/.well-known/openid-configuration',
]);

it('derives the issuer from the APP_URL origin', function (string $appUrl, string $issuer) {
    config(['app.url' => $appUrl]);

    expect(OAuthUrls::issuer())->toBe($issuer);
})->with([
    'trailing slash and default port' => ['https://Admin.Example.com:443/', 'https://admin.example.com'],
    'path' => ['https://admin.example.com/shopmade', 'https://admin.example.com'],
    'custom port' => ['http://localhost:8000', 'http://localhost:8000'],
]);

it('points the MCP challenge at the path-suffixed resource metadata', function () {
    expect(OAuthUrls::resourceMetadata())->toBe('https://shop.example/.well-known/oauth-protected-resource/mcp/agents');
});

it('matches the MCP resource regardless of case, default port and trailing slash', function (string $resource, bool $matches) {
    expect(OAuthUrls::isMcpResource($resource))->toBe($matches);
})->with([
    'exact' => ['https://shop.example/mcp/agents', true],
    'uppercase host and trailing slash' => ['HTTPS://SHOP.EXAMPLE:443/mcp/agents/', true],
    'other host' => ['https://evil.example/mcp/agents', false],
    'other path' => ['https://shop.example/mcp', false],
    'plain http' => ['http://shop.example/mcp/agents', false],
    'fragment' => ['https://shop.example/mcp/agents#x', false],
]);
