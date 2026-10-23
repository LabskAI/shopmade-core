<?php

namespace App\Http\Controllers\Mcp;

use App\Core\Mcp\McpContext;
use App\Core\Mcp\McpError;
use App\Core\Mcp\McpServer;
use App\Core\Mcp\McpSessions;
use App\Core\Mcp\ProtocolVersion;
use App\Http\Controllers\Controller;
use App\Models\McpSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use JsonException;
use stdClass;

/**
 * MCP Streamable HTTP transport (JSON responses only, no SSE): POST carries JSON-RPC, DELETE ends the
 * session, GET answers 405 because every tool answers in its POST response and there is nothing to stream.
 */
class McpController extends Controller
{
    private const SessionHeader = 'Mcp-Session-Id';

    private const VersionHeader = 'MCP-Protocol-Version';

    private const JsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

    public function __construct(
        private McpServer $server,
        private McpSessions $sessions,
        private ProtocolVersion $versions,
    ) {}

    public function handle(Request $request): JsonResponse|Response
    {
        $context = McpContext::fromRequest($request);
        $body = $request->getContent();
        $maxBytes = (int) config('agents.mcp.max_body_bytes', 262144);

        if (strlen($body) > $maxBytes) {
            return $this->failure(413, McpError::invalidRequest("Request body is larger than {$maxBytes} bytes.", 'Send smaller arguments, e.g. a shorter brief.'));
        }

        try {
            $decoded = json_decode($body, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->failure(400, McpError::parseError());
        }

        if (is_array($decoded)) {
            return $this->batch($request, $decoded, $context);
        }

        if (! $decoded instanceof stdClass) {
            return $this->failure(400, McpError::invalidRequest('The body must be a JSON-RPC message object.', 'Send {"jsonrpc": "2.0", "id": 1, "method": "..."}.'));
        }

        $message = (array) json_decode($body, true);
        $kind = $this->kind($message);

        if ($kind === 'invalid') {
            return $this->failure(400, $this->invalidMessage(), $this->idOf($message));
        }

        if ($kind === 'request' && $message['method'] === 'initialize') {
            return $this->initialize($message, $context);
        }

        $session = $this->session($request, $context);

        if ($session instanceof JsonResponse) {
            return $session;
        }

        $version = $this->protocolVersion($request, $session);

        if ($version instanceof JsonResponse) {
            return $version;
        }

        if ($kind !== 'request') {
            $this->server->notify($session);

            return response()->noContent(202);
        }

        $this->sessions->touch($session);

        return $this->json($this->server->request($message, $context, $version));
    }

    public function stream(): JsonResponse
    {
        return $this->failure(405, McpError::invalidRequest(
            'This server does not offer an SSE stream.',
            'POST JSON-RPC messages to this URL; every tool answers in its response.',
        ), headers: ['Allow' => 'POST, DELETE']);
    }

    public function destroy(Request $request): JsonResponse|Response
    {
        $session = $this->session($request, McpContext::fromRequest($request));

        if ($session instanceof JsonResponse) {
            return $session;
        }

        $this->sessions->end($session);

        return response()->noContent();
    }

    public function preflight(): Response
    {
        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function initialize(array $message, McpContext $context): JsonResponse
    {
        [$response, $session] = $this->server->initialize($message, $context);

        return $this->json($response, headers: $session === null ? [] : [self::SessionHeader => $session->id]);
    }

    /**
     * JSON-RPC batches exist only in protocol 2025-03-26; initialize can never be batched, and a batch holds at
     * most agents.mcp.max_batch messages.
     *
     * @param  array<int, mixed>  $items
     */
    private function batch(Request $request, array $items, McpContext $context): JsonResponse|Response
    {
        if ($items === []) {
            return $this->failure(400, McpError::invalidRequest('Empty batch.', 'Send at least one JSON-RPC message.'));
        }

        $maxBatch = max(1, (int) config('agents.mcp.max_batch', 10));

        if (count($items) > $maxBatch) {
            return $this->failure(400, McpError::invalidRequest(
                "A batch may hold at most {$maxBatch} messages; this one has ".count($items).'.',
                "Send at most {$maxBatch} messages per POST.",
            ));
        }

        $messages = array_map(fn (mixed $item): mixed => $item instanceof stdClass ? json_decode((string) json_encode($item), true) : null, $items);

        foreach ($messages as $message) {
            if (is_array($message) && ($message['method'] ?? null) === 'initialize') {
                return $this->failure(400, McpError::invalidRequest('initialize cannot be part of a batch.', 'Send initialize on its own.'));
            }
        }

        $session = $this->session($request, $context);

        if ($session instanceof JsonResponse) {
            return $session;
        }

        $version = $this->protocolVersion($request, $session);

        if ($version instanceof JsonResponse) {
            return $version;
        }

        if (! $this->versions->allowsBatches($version)) {
            return $this->failure(400, McpError::invalidRequest(
                "JSON-RPC batches are not supported in protocol {$version}.",
                'Send one message per POST.',
            ));
        }

        $this->sessions->touch($session);

        $responses = [];

        foreach ($messages as $message) {
            $kind = is_array($message) ? $this->kind($message) : 'invalid';

            if ($kind === 'invalid') {
                $responses[] = $this->server->error(is_array($message) ? $this->idOf($message) : null, $this->invalidMessage());
            } elseif ($kind === 'request') {
                $responses[] = $this->server->request($message, $context, $version);
            }
        }

        return $responses === [] ? response()->noContent(202) : $this->json($responses);
    }

    /**
     * The caller's live session from the Mcp-Session-Id header, or the 400/404 response to send.
     */
    private function session(Request $request, McpContext $context): McpSession|JsonResponse
    {
        $id = trim((string) $request->header(self::SessionHeader, ''));

        if ($id === '') {
            return $this->failure(400, McpError::invalidRequest(
                'Missing Mcp-Session-Id header.',
                'Call initialize first and send the Mcp-Session-Id it returns on every request.',
            ));
        }

        return $this->sessions->find($id, $context) ?? $this->failure(404, McpError::notFound(
            'Session not found or expired.',
            'Start a new session: send initialize without an Mcp-Session-Id header.',
        ));
    }

    /**
     * The MCP-Protocol-Version header when sent (it must be one we support), else the session's negotiated version.
     */
    private function protocolVersion(Request $request, McpSession $session): string|JsonResponse
    {
        $header = trim((string) $request->header(self::VersionHeader, ''));

        if ($header === '') {
            return (string) $session->protocol_version;
        }

        if (! $this->versions->isSupported($header)) {
            return $this->failure(400, McpError::invalidRequest(
                "Unsupported MCP-Protocol-Version: {$header}.",
                'Supported: '.implode(', ', $this->versions->supported()).'.',
            ));
        }

        return $header;
    }

    /**
     * request (method + id), notification (method, no id), response (result or error + id) or invalid.
     *
     * @param  array<string, mixed>  $message
     */
    private function kind(array $message): string
    {
        if (($message['jsonrpc'] ?? null) !== '2.0') {
            return 'invalid';
        }

        if (array_key_exists('method', $message)) {
            if (! is_string($message['method']) || $message['method'] === '') {
                return 'invalid';
            }

            if (! array_key_exists('id', $message)) {
                return 'notification';
            }

            return is_int($message['id']) || is_string($message['id']) ? 'request' : 'invalid';
        }

        return array_key_exists('id', $message) && (array_key_exists('result', $message) || array_key_exists('error', $message))
            ? 'response'
            : 'invalid';
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function idOf(array $message): int|string|null
    {
        $id = $message['id'] ?? null;

        return is_int($id) || is_string($id) ? $id : null;
    }

    private function invalidMessage(): McpError
    {
        return McpError::invalidRequest(
            'Invalid JSON-RPC message.',
            'Send {"jsonrpc": "2.0", "method": "...", "id": <string|number>} (omit id for notifications).',
        );
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function failure(int $status, McpError $error, int|string|null $id = null, array $headers = []): JsonResponse
    {
        return $this->json($this->server->error($id, $error), $status, $headers);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function json(mixed $payload, int $status = 200, array $headers = []): JsonResponse
    {
        return response()->json($payload, $status, $headers, self::JsonFlags);
    }
}
