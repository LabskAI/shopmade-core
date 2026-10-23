<?php

require_once __DIR__.'/OAuthHelpers.php';

beforeEach(function () {
    oauthUseTestHost();
});

it('answers CORS preflight requests on every machine endpoint', function (string $path) {
    $this->call('OPTIONS', $path, server: [
        'HTTP_ORIGIN' => 'http://localhost:6274',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type, mcp-protocol-version',
    ])
        ->assertNoContent()
        ->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        ->assertHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type, Mcp-Session-Id, MCP-Protocol-Version, Last-Event-ID')
        ->assertHeader('Access-Control-Expose-Headers', 'Mcp-Session-Id, WWW-Authenticate');
})->with([
    '/.well-known/oauth-protected-resource',
    '/.well-known/oauth-protected-resource/mcp/agents',
    '/.well-known/oauth-authorization-server',
    '/.well-known/openid-configuration',
    '/oauth/register',
    '/oauth/token',
    '/oauth/revoke',
]);

it('sends CORS headers and no cookies from the stateless endpoints', function () {
    $response = $this->getJson('/.well-known/oauth-authorization-server', ['Origin' => 'http://localhost:6274']);

    $response->assertOk()->assertHeader('Access-Control-Allow-Origin', '*');
    expect($response->headers->getCookies())->toBeEmpty();
});

it('keeps CORS headers on error responses', function () {
    $this->postJson('/oauth/token', [], ['Origin' => 'http://localhost:6274'])
        ->assertBadRequest()
        ->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertJsonPath('error', 'invalid_request');
});
