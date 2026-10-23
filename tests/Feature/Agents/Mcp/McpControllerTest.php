<?php

use App\Core\OAuth\OAuthUrls;
use App\Models\McpSession;

use function Pest\Laravel\freezeTime;

require_once __DIR__.'/McpHelpers.php';

beforeEach(fn () => config(['agents.company' => 'demo']));

it('initializes a session with the client version and returns the session header', function () {
    [$token, $bearer] = mcpToken();

    $response = mcpPost(mcpMessage('initialize', [
        'protocolVersion' => '2025-06-18',
        'capabilities' => (object) [],
        'clientInfo' => ['name' => 'claude-ai', 'version' => '0.1.0'],
    ]), $bearer);

    $response->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertJsonPath('id', 1)
        ->assertJsonPath('result.protocolVersion', '2025-06-18')
        ->assertJsonPath('result.capabilities.tools.listChanged', false)
        ->assertJsonPath('result.serverInfo.name', 'shopmade-agents');
    expect($response->getContent())->toContain('"resources":{}', '"prompts":{}')
        ->and($response->json('result.instructions'))->toContain('Shopmade site tools for demo', 'page_history');
    $this->assertDatabaseHas('mcp_sessions', [
        'id' => $response->headers->get('Mcp-Session-Id'),
        'grant_id' => $token->grant_id,
        'user_id' => $token->user_id,
        'protocol_version' => '2025-06-18',
    ]);
});

it('answers with its latest protocol version when the client asks for an unsupported one', function () {
    [, $bearer] = mcpToken();

    mcpPost(mcpMessage('initialize', ['protocolVersion' => '2024-11-05', 'capabilities' => (object) []]), $bearer)
        ->assertOk()
        ->assertJsonPath('result.protocolVersion', '2025-11-25');
});

it('returns 401 with the protected resource challenge and -32001 without a token', function () {
    $response = mcpPost(mcpMessage('initialize', ['protocolVersion' => '2025-06-18']), null);

    $response->assertUnauthorized()
        ->assertHeader('WWW-Authenticate', 'Bearer resource_metadata="'.OAuthUrls::resourceMetadata().'", scope="director"')
        ->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertJsonPath('error.code', -32001)
        ->assertJsonPath('id', null);
    expect($response->headers->get('Access-Control-Expose-Headers'))->toContain('WWW-Authenticate', 'Mcp-Session-Id');
    $this->assertDatabaseCount('mcp_sessions', 0);
});

it('returns 401 with invalid_token for unknown, revoked-grant and foreign-resource tokens', function (string $case) {
    [$token, $bearer] = mcpToken(tokenAttributes: $case === 'foreign resource' ? ['resource' => 'https://other.example/mcp/agents'] : []);

    if ($case === 'revoked grant') {
        $token->grant->forceFill(['revoked_at' => now()])->save();
    }

    $response = mcpPost(mcpMessage('initialize', ['protocolVersion' => '2025-06-18']), $case === 'unknown' ? 'not-a-token' : $bearer);

    $response->assertUnauthorized()->assertJsonPath('error.code', -32001);
    expect($response->headers->get('WWW-Authenticate'))->toContain('error="invalid_token"');
})->with(['unknown', 'revoked grant', 'foreign resource']);

it('accepts a token bound to this MCP resource', function () {
    [, $bearer] = mcpToken(tokenAttributes: ['resource' => OAuthUrls::mcpResource()]);

    mcpPost(mcpMessage('initialize', ['protocolVersion' => '2025-06-18']), $bearer)->assertOk();
});

it('returns 400 when a request after initialize has no session header', function () {
    [, $bearer] = mcpToken();

    mcpPost(mcpMessage('tools/list'), $bearer)
        ->assertBadRequest()
        ->assertJsonPath('error.code', -32600);
});

it('returns 404 for unknown sessions, expired sessions and sessions of another grant', function () {
    [$token, $bearer] = mcpToken();
    [, $otherBearer] = mcpToken();
    $otherSession = mcpSession($otherBearer);
    $own = fn (int $idleMinutes) => McpSession::factory()->create([
        'grant_id' => $token->grant_id,
        'user_id' => $token->user_id,
        'client_id' => $token->client_id,
        'last_seen_at' => now()->subMinutes($idleMinutes),
    ]);

    mcpPost(mcpMessage('ping'), $bearer, 'no-such-session')->assertNotFound()->assertJsonPath('error.code', -32004);
    mcpPost(mcpMessage('ping'), $bearer, $otherSession)->assertNotFound();
    mcpPost(mcpMessage('ping'), $bearer, $own(1441)->id)->assertNotFound();
    mcpPost(mcpMessage('ping'), $bearer, $own(1439)->id)->assertOk();
});

it('answers ping inside a live session', function () {
    [$bearer, $session] = mcpDirector();

    mcpPost(mcpMessage('ping', id: 'abc'), $bearer, $session)
        ->assertOk()
        ->assertExactJson(['jsonrpc' => '2.0', 'id' => 'abc', 'result' => []]);
});

it('ends the session on DELETE and answers 404 when it is reused', function () {
    [$bearer, $session] = mcpDirector();

    $this->call('DELETE', MCP_ENDPOINT, server: $this->transformHeadersToServerVars([
        'Authorization' => 'Bearer '.$bearer,
        'Mcp-Session-Id' => $session,
    ]))->assertNoContent();

    mcpPost(mcpMessage('tools/list'), $bearer, $session)->assertNotFound();
    expect(McpSession::query()->find($session)->ended_at)->not->toBeNull();
});

it('answers GET with 405 because there is no event stream', function () {
    $this->get(MCP_ENDPOINT)
        ->assertMethodNotAllowed()
        ->assertHeader('Allow', 'POST, DELETE')
        ->assertHeader('Access-Control-Allow-Origin', '*');
});

it('answers the CORS preflight without a token', function () {
    $response = $this->call('OPTIONS', MCP_ENDPOINT, server: $this->transformHeadersToServerVars([
        'Origin' => 'http://localhost:6274',
        'Access-Control-Request-Method' => 'POST',
        'Access-Control-Request-Headers' => 'authorization, content-type, mcp-session-id, mcp-protocol-version',
    ]));

    $response->assertNoContent()->assertHeader('Access-Control-Allow-Origin', '*');
    expect($response->headers->get('Access-Control-Allow-Headers'))
        ->toContain('Authorization', 'Content-Type', 'Mcp-Session-Id', 'MCP-Protocol-Version', 'Last-Event-ID')
        ->and($response->headers->get('Access-Control-Allow-Methods'))->toContain('POST', 'DELETE');
});

it('rejects a body over the configured limit with 413 and -32600', function () {
    config(['agents.mcp.max_body_bytes' => 1024]);
    [$bearer, $session] = mcpDirector();

    mcpPost(mcpMessage('tools/call', ['name' => 'chat_post', 'arguments' => ['thread_ref' => 'thread:1', 'message' => str_repeat('a', 2000)]]), $bearer, $session)
        ->assertStatus(413)
        ->assertJsonPath('error.code', -32600);
});

it('returns -32700 for a body that is not JSON', function () {
    [$bearer, $session] = mcpDirector();

    mcpPost('{"jsonrpc": "2.0", "method": ', $bearer, $session)
        ->assertBadRequest()
        ->assertJsonPath('error.code', -32700)
        ->assertJsonPath('id', null);
});

it('returns -32600 for a message without jsonrpc 2.0', function () {
    [$bearer, $session] = mcpDirector();

    mcpPost(['id' => 7, 'method' => 'ping'], $bearer, $session)
        ->assertBadRequest()
        ->assertJsonPath('error.code', -32600)
        ->assertJsonPath('id', 7);
});

it('accepts notifications with 202 and no body', function () {
    [$bearer, $session] = mcpDirector();

    $response = mcpPost(mcpMessage('notifications/initialized', id: null), $bearer, $session);

    $response->assertAccepted();
    expect($response->getContent())->toBe('');
});

it('returns -32601 for an unknown method', function () {
    [$bearer, $session] = mcpDirector();

    mcpPost(mcpMessage('sampling/createMessage'), $bearer, $session)
        ->assertOk()
        ->assertJsonPath('error.code', -32601);
});

it('rejects an unsupported MCP-Protocol-Version header with 400', function () {
    [$bearer, $session] = mcpDirector();

    mcpPost(mcpMessage('ping'), $bearer, $session, ['MCP-Protocol-Version' => '2099-01-01'])->assertBadRequest();
    mcpPost(mcpMessage('ping'), $bearer, $session, ['MCP-Protocol-Version' => '2025-06-18'])->assertOk();
});

it('answers a batch for a 2025-03-26 session, skipping notifications', function () {
    [$bearer, $session] = mcpDirector(protocolVersion: '2025-03-26');

    $response = mcpPost([
        mcpMessage('ping', id: 1),
        mcpMessage('notifications/initialized', id: null),
        mcpMessage('no/such-method', id: 2),
    ], $bearer, $session);

    $response->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.id', 1)
        ->assertJsonPath('0.result', [])
        ->assertJsonPath('1.id', 2)
        ->assertJsonPath('1.error.code', -32601);
});

it('rejects a batch of more than 10 messages with 400 and -32600 before running any of them', function () {
    mcpFront(['name' => 'Demo Cleaning']);
    [$bearer, $session] = mcpDirector(protocolVersion: '2025-03-26');
    $calls = array_map(fn (int $id): array => mcpMessage('tools/call', ['name' => 'pages_list', 'arguments' => (object) []], id: $id), range(1, 11));

    $response = mcpPost($calls, $bearer, $session);

    $response->assertBadRequest()
        ->assertJsonPath('error.code', -32600)
        ->assertJsonPath('error.message', 'A batch may hold at most 10 messages; this one has 11. Send at most 10 messages per POST.');
});

it('counts resources and prompts against the per-token limit, message by message inside a batch', function () {
    config(['agents.mcp.rate_limit_per_minute' => 2]);
    freezeTime();
    mcpFront(['name' => 'Demo Cleaning']);
    [$bearer, $session] = mcpDirector(protocolVersion: '2025-03-26');

    $response = mcpPost([
        mcpMessage('resources/read', ['uri' => 'shopmade://demo/facts'], id: 1),
        mcpMessage('prompts/get', ['name' => 'edit-site'], id: 2),
        mcpMessage('resources/read', ['uri' => 'shopmade://demo/facts'], id: 3),
    ], $bearer, $session);

    $response->assertOk()
        ->assertJsonPath('0.result.contents.0.uri', 'shopmade://demo/facts')
        ->assertJsonPath('1.result.messages.0.role', 'user')
        ->assertJsonPath('2.id', 3)
        ->assertJsonPath('2.error.code', -32003);
    expect($response->json('2.error.data.retry_after'))->toBeInt()->toBeGreaterThan(0);
});

it('shares the per-token limit between resources and tool calls and never counts ping', function () {
    config(['agents.mcp.rate_limit_per_minute' => 1]);
    freezeTime();
    mcpFront(['name' => 'Demo Cleaning']);
    [$bearer, $session] = mcpDirector();
    mcpPost(mcpMessage('resources/read', ['uri' => 'shopmade://demo/facts']), $bearer, $session)->assertJsonPath('result.contents.0.uri', 'shopmade://demo/facts');

    $toolCall = mcpCall('pages_list', [], $bearer, $session);

    $toolCall->assertJsonPath('error.code', -32003);
    mcpPost(mcpMessage('ping'), $bearer, $session)->assertExactJson(['jsonrpc' => '2.0', 'id' => 1, 'result' => []]);
});

it('counts a tool call that fails before it reaches a tool against the per-token limit', function (array|string $params) {
    config(['agents.mcp.rate_limit_per_minute' => 2]);
    freezeTime();
    [$bearer, $session] = mcpDirector();
    $call = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => $params];

    mcpPost($call, $bearer, $session)->assertJsonPath('error.code', -32602);
    mcpPost($call, $bearer, $session)->assertJsonPath('error.code', -32602);
    mcpPost($call, $bearer, $session)->assertJsonPath('error.code', -32003);
})->with([
    'params that are a list' => [['pages_list']],
    'params that are a string' => ['pages_list'],
    'no tool name' => [['arguments' => (object) []]],
    'an unknown tool' => [['name' => 'build_delete']],
]);

it('rejects batches in 2025-06-18 sessions and initialize inside a batch', function () {
    [$bearer, $session] = mcpDirector();

    mcpPost([mcpMessage('ping', id: 1), mcpMessage('ping', id: 2)], $bearer, $session)
        ->assertBadRequest()
        ->assertJsonPath('error.code', -32600);
    mcpPost([mcpMessage('initialize', ['protocolVersion' => '2025-03-26'])], $bearer)
        ->assertBadRequest()
        ->assertJsonPath('error.code', -32600);
});
