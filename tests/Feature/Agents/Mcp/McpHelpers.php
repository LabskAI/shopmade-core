<?php

use App\Models\ConnectorGrant;
use App\Models\OAuthAccessToken;
use App\Models\User;
use Database\Factories\OAuthAccessTokenFactory;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Yaml\Yaml;

const MCP_ENDPOINT = '/mcp/agents';

/**
 * A connector grant and access token (resolved by the real AccessTokens) for a super admin unless a user is given.
 *
 * @param  list<string>  $scopes
 * @param  array<string, mixed>  $tokenAttributes
 * @return array{0: OAuthAccessToken, 1: string}
 */
function mcpToken(array $scopes = ['director'], ?User $user = null, array $tokenAttributes = []): array
{
    $grant = ConnectorGrant::factory()->create(array_filter(['scopes' => $scopes, 'user_id' => $user?->id]));

    return OAuthAccessTokenFactory::createWithPlaintext(['grant_id' => $grant->id, 'scopes' => $scopes, ...$tokenAttributes]);
}

/**
 * POSTs a JSON-RPC payload (array, or a raw string body) to the MCP endpoint.
 *
 * @param  array<int|string, mixed>|string  $payload
 * @param  array<string, string>  $headers
 */
function mcpPost(array|string $payload, ?string $bearer, ?string $session = null, array $headers = []): TestResponse
{
    $headers = array_filter([
        'Authorization' => $bearer === null ? null : 'Bearer '.$bearer,
        'Accept' => 'application/json, text/event-stream',
        'Content-Type' => 'application/json',
        'Mcp-Session-Id' => $session,
        ...$headers,
    ], fn (?string $value): bool => $value !== null);

    return test()->call('POST', MCP_ENDPOINT, [], [], [], test()->transformHeadersToServerVars($headers), is_string($payload) ? $payload : json_encode($payload));
}

/**
 * Runs initialize and returns the session id.
 */
function mcpSession(string $bearer, string $protocolVersion = '2025-06-18'): string
{
    $response = mcpPost(mcpMessage('initialize', [
        'protocolVersion' => $protocolVersion,
        'capabilities' => (object) [],
        'clientInfo' => ['name' => 'pest', 'version' => '1.0.0'],
    ]), $bearer);

    return (string) $response->headers->get('Mcp-Session-Id');
}

/**
 * @param  array<string, mixed>|object  $params
 * @return array<string, mixed>
 */
function mcpMessage(string $method, array|object $params = [], int|string|null $id = 1): array
{
    $message = ['jsonrpc' => '2.0', 'method' => $method, 'params' => $params === [] ? (object) [] : $params];

    return $id === null ? $message : ['jsonrpc' => '2.0', 'id' => $id, ...$message];
}

/**
 * @param  array<string, mixed>  $arguments
 */
function mcpCall(string $tool, array $arguments, string $bearer, string $session): TestResponse
{
    return mcpPost(mcpMessage('tools/call', ['name' => $tool, 'arguments' => $arguments === [] ? (object) [] : $arguments]), $bearer, $session);
}

/**
 * A connected director: token plus an initialized session.
 *
 * @param  list<string>  $scopes
 * @return array{0: string, 1: string, 2: OAuthAccessToken}
 */
function mcpDirector(array $scopes = ['director'], string $protocolVersion = '2025-06-18'): array
{
    [$token, $bearer] = mcpToken($scopes);

    return [$bearer, mcpSession($bearer, $protocolVersion), $token];
}

/**
 * A throwaway repo root with fronts/<company>/facts.yaml and skills, used as agents.paths.repo.
 *
 * @param  array<string, mixed>  $facts
 * @param  array<string, string>  $skills  name => markdown
 */
function mcpFront(array $facts, array $skills = [], string $company = 'demo'): string
{
    $root = sys_get_temp_dir().'/shopmade-mcp-'.bin2hex(random_bytes(6));

    File::ensureDirectoryExists("{$root}/fronts/{$company}/skills");
    File::put("{$root}/fronts/{$company}/facts.yaml", Yaml::dump($facts, 4));

    foreach ($skills as $name => $markdown) {
        File::put("{$root}/fronts/{$company}/skills/{$name}.md", $markdown);
    }

    config(['agents.paths.repo' => $root]);
    test()->beforeApplicationDestroyed(fn () => File::deleteDirectory($root));

    return $root;
}
