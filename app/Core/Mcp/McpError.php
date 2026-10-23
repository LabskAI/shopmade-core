<?php

namespace App\Core\Mcp;

use App\Core\Agents\AgentRefusedException;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * A JSON-RPC error answered to the MCP client. The message is written for the director model
 * (what went wrong, then what to do next) and repeats data.hint, because some clients only
 * show the message. It never carries internal details such as paths, SQL or secrets.
 */
class McpError extends RuntimeException implements ShouldntReport
{
    public const ParseError = -32700;

    public const InvalidRequest = -32600;

    public const MethodNotFound = -32601;

    public const InvalidParams = -32602;

    public const InternalError = -32603;

    public const Unauthorized = -32001;

    public const Forbidden = -32002;

    public const RateLimited = -32003;

    public const NotFound = -32004;

    public const PathRefused = -32005;

    public const BudgetExhausted = -32006;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(int $code, string $message, public readonly array $data = [])
    {
        parent::__construct($message, $code);
    }

    public static function parseError(): self
    {
        return self::withHint(self::ParseError, 'Parse error: the body is not valid JSON.', 'Send one JSON-RPC 2.0 message as UTF-8 JSON.');
    }

    public static function invalidRequest(string $message, ?string $hint = null): self
    {
        return self::withHint(self::InvalidRequest, $message, $hint);
    }

    public static function methodNotFound(string $method): self
    {
        return self::withHint(
            self::MethodNotFound,
            'Method not found: '.Str::limit($method, 100).'.',
            'Supported: initialize, ping, tools/list, tools/call, resources/list, resources/templates/list, resources/read, prompts/list, prompts/get.',
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function invalidParams(string $message, ?string $hint = null, array $data = []): self
    {
        return self::withHint(self::InvalidParams, $message, $hint, $data);
    }

    public static function unauthorized(string $message, string $hint): self
    {
        return self::withHint(self::Unauthorized, $message, $hint);
    }

    public static function forbidden(string $message, string $hint, string $reason = 'forbidden'): self
    {
        return self::withHint(self::Forbidden, $message, $hint, ['reason' => $reason]);
    }

    public static function rateLimited(int $retryAfter, int $perMinute): self
    {
        return self::withHint(
            self::RateLimited,
            "Rate limited: at most {$perMinute} requests (tool calls, resources and prompts) per minute per token.",
            "Retry in {$retryAfter} seconds.",
            ['retry_after' => $retryAfter],
        );
    }

    public static function notFound(string $message, string $hint): self
    {
        return self::withHint(self::NotFound, $message, $hint, ['reason' => 'not_found']);
    }

    public static function pathRefused(string $message, string $hint): self
    {
        return self::withHint(self::PathRefused, $message, $hint, ['reason' => 'path_refused']);
    }

    public static function internal(string $what): self
    {
        return self::withHint(
            self::InternalError,
            'Internal error while handling '.Str::limit($what, 100).'.',
            'The failure was logged on the server. Retry once; if it persists, check build_get or ask a human to look at the logs.',
        );
    }

    /**
     * Maps a refusal from the agents services onto the connector error codes (ARCHITECTURE §4).
     */
    public static function fromRefusal(AgentRefusedException $refusal): self
    {
        return self::withHint($refusal->mcpCode(), $refusal->getMessage(), $refusal->hint, ['reason' => $refusal->reason]);
    }

    /**
     * Policy refusals (scope, risk gate, rate limit, fence, budget) as opposed to bad input or failures.
     */
    public function isPolicyRefusal(): bool
    {
        return in_array($this->getCode(), [self::Forbidden, self::RateLimited, self::PathRefused, self::BudgetExhausted], true);
    }

    /**
     * @return array{code: int, message: string, data?: array<string, mixed>}
     */
    public function toError(): array
    {
        $error = ['code' => $this->getCode(), 'message' => $this->getMessage()];

        if ($this->data !== []) {
            $error['data'] = $this->data;
        }

        return $error;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function withHint(int $code, string $message, ?string $hint, array $data = []): self
    {
        $hint = $hint === null || trim($hint) === '' ? null : trim($hint);
        $message = trim($message);

        if ($hint !== null) {
            $message = rtrim($message, '.').'. '.$hint;
            $data['hint'] = $hint;
        }

        return new self($code, $message, $data);
    }
}
