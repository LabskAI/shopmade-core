<?php

namespace App\Core\Mcp\Tools;

use App\Core\Agents\AgentRefusedException;
use App\Core\Mcp\ArgumentValidator;
use App\Core\Mcp\McpContext;
use App\Core\Mcp\McpError;
use App\Core\Mcp\McpRateLimit;
use App\Core\Mcp\ProtocolVersion;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs one tools/call: the per-token request limit (counted here so a rate-limited call is audited too),
 * scope and capability checks, argument validation, the tool itself, error mapping and the audit row.
 */
class ToolInvoker
{
    private const JsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

    public function __construct(
        private ToolRegistry $tools,
        private ArgumentValidator $validator,
        private ToolAudit $audit,
        private ProtocolVersion $versions,
        private McpRateLimit $rateLimit,
    ) {}

    /**
     * @return array<string, mixed> the CallToolResult
     *
     * @throws McpError
     */
    public function call(mixed $name, mixed $arguments, McpContext $context, string $protocolVersion): array
    {
        $startedAt = now();
        $started = hrtime(true);
        $toolName = is_string($name) ? $name : '';
        $rawArguments = is_array($arguments) ? $arguments : [];

        try {
            $this->rateLimit->hit($context);

            $tool = $this->resolve($toolName, $arguments, $context);
            $result = $tool->handle($this->validator->validate($tool->inputSchema(), $rawArguments), $context);
        } catch (Throwable $exception) {
            $error = $this->toError($exception, $toolName);
            $refused = $exception instanceof AgentRefusedException || $error->isPolicyRefusal();

            $this->record(fn () => $this->audit->record($context, $toolName, $rawArguments, $startedAt, $this->elapsedMs($started), $error, $refused));

            throw $error;
        }

        $this->record(fn () => $this->audit->record($context, $toolName, $rawArguments, $startedAt, $this->elapsedMs($started), result: $result->data));

        return $this->format($result, $protocolVersion);
    }

    /**
     * A failing audit write is reported but never turns a finished call into an error.
     */
    private function record(callable $write): void
    {
        try {
            $write();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function resolve(string $name, mixed $arguments, McpContext $context): Tool
    {
        if ($name === '') {
            throw McpError::invalidParams('params.name is required.', 'Pass the tool name from tools/list.');
        }

        $tool = $this->tools->find($name) ?? throw McpError::invalidParams('Unknown tool: '.Str::limit($name, 100).'.', 'Call tools/list for the available tools.');

        if ($arguments !== null && (! is_array($arguments) || ($arguments !== [] && array_is_list($arguments)))) {
            throw McpError::invalidParams('params.arguments must be an object.', 'Send the arguments as a JSON object.');
        }

        if (! $tool->allows($context)) {
            throw McpError::forbidden(
                "{$name} needs the {$tool->scope()->value} scope; this token has: {$context->scopeList()}.",
                'Reconnect the connector and consent to the scope, or ask an admin to change the grant under Admin → Connectors.',
            );
        }

        if ($context->user->cannot('site.edit')) {
            throw McpError::forbidden(
                "{$context->user->name} can no longer edit the website.",
                'Ask an admin to restore the role, or disconnect the connector.',
            );
        }

        return $tool;
    }

    private function toError(Throwable $exception, string $toolName): McpError
    {
        if ($exception instanceof McpError) {
            return $exception;
        }

        if ($exception instanceof AgentRefusedException) {
            return McpError::fromRefusal($exception);
        }

        report($exception);

        return McpError::internal($toolName === '' ? 'tools/call' : $toolName);
    }

    /**
     * @return array<string, mixed>
     */
    private function format(ToolResult $result, string $protocolVersion): array
    {
        $content = [['type' => 'text', 'text' => (string) json_encode($result->data, self::JsonFlags)]];

        foreach ($result->images as $image) {
            $content[] = ['type' => 'image', 'data' => $image['data'], 'mimeType' => $image['mimeType']];
        }

        $formatted = ['content' => $content];

        if ($this->versions->hasStructuredContent($protocolVersion)) {
            $formatted['structuredContent'] = (object) $result->data;
        }

        $formatted['isError'] = false;

        return $formatted;
    }

    private function elapsedMs(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
