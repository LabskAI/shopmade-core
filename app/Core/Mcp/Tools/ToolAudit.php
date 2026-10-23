<?php

namespace App\Core\Mcp\Tools;

use App\Core\Mcp\McpContext;
use App\Core\Mcp\McpError;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * One log entry (mcp.tool_call) per connector tool call, with the arguments redacted and capped.
 */
class ToolAudit
{
    public const Redacted = '[redacted]';

    private const MaxInputBytes = 4096;

    private const MaxStringChars = 1000;

    private const SensitiveKey = '/token|secret|key|password|passwd|authorization|credential|cookie/i';

    /**
     * @param  array<string, mixed>  $arguments  as sent by the client
     * @param  array<string, mixed>  $result  tool data on success
     */
    public function record(
        McpContext $context,
        string $tool,
        array $arguments,
        Carbon $startedAt,
        int $latencyMs,
        ?McpError $error = null,
        bool $refused = false,
        array $result = [],
    ): void {
        Log::info('mcp.tool_call', [
            'actor' => $context->actor->key(),
            'tool' => Str::limit($tool === '' ? '(none)' : mb_scrub($tool, 'UTF-8'), 60, ''),
            'input' => $this->input($arguments),
            'status' => $error === null ? 'ok' : ($refused ? 'refused' : 'error'),
            'error_code' => $error?->getCode(),
            'error_message' => $error === null ? null : Str::limit(mb_scrub($error->getMessage(), 'UTF-8'), 1000),
            'latency_ms' => max(0, $latencyMs),
            'started_at' => $startedAt->toIso8601String(),
        ]);
    }

    /**
     * Values of secret-looking keys are dropped, strings are capped,
     * invalid UTF-8 is replaced, and the whole input is capped at 4 KB of JSON.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>|null
     */
    public function input(array $arguments): ?array
    {
        if ($arguments === []) {
            return null;
        }

        $json = (string) json_encode($this->redact($arguments), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        if (strlen($json) <= self::MaxInputBytes) {
            return (array) json_decode($json, true);
        }

        return ['_truncated' => true, 'excerpt' => mb_strcut($json, 0, self::MaxInputBytes - 200, 'UTF-8')];
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @return array<int|string, mixed>
     */
    private function redact(array $values): array
    {
        $redacted = [];

        foreach ($values as $key => $value) {
            $redacted[$key] = match (true) {
                is_string($key) && preg_match(self::SensitiveKey, $key) === 1 => self::Redacted,
                is_array($value) => $this->redact($value),
                is_string($value) => Str::limit($value, self::MaxStringChars, '…'),
                default => $value,
            };
        }

        return $redacted;
    }
}
