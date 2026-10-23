<?php

namespace App\Core\Mcp\Tools;

use App\Core\Mcp\McpContext;
use App\Enums\ConnectorScope;
use stdClass;

/**
 * One MCP tool. The input schema is what tools/list shows and what ArgumentValidator enforces;
 * handle() receives validated arguments and either returns a result or throws McpError /
 * AgentRefusedException, which the invoker maps to JSON-RPC errors.
 */
abstract class Tool
{
    abstract public function name(): string;

    abstract public function title(): string;

    abstract public function description(): string;

    /**
     * JSON Schema (object) for the arguments.
     *
     * @return array<string, mixed>
     */
    abstract public function inputSchema(): array;

    /**
     * @param  array<string, mixed>  $arguments
     */
    abstract public function handle(array $arguments, McpContext $context): ToolResult;

    public function scope(): ConnectorScope
    {
        return ConnectorScope::Director;
    }

    /**
     * Whether a token may call this tool: it holds the tool's scope, or the tool only reads (operator) and the token
     * may write (director).
     */
    public function allows(McpContext $context): bool
    {
        return $context->hasScope($this->scope())
            || ($this->scope() === ConnectorScope::Operator && $context->hasScope(ConnectorScope::Director));
    }

    /**
     * @return array{readOnlyHint: bool, destructiveHint: bool, idempotentHint: bool, openWorldHint: bool}
     */
    public function annotations(): array
    {
        return ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false];
    }

    /**
     * The tools/list entry.
     *
     * @return array{name: string, title: string, description: string, inputSchema: array<string, mixed>, annotations: array<string, mixed>}
     */
    public function definition(): array
    {
        return [
            'name' => $this->name(),
            'title' => $this->title(),
            'description' => $this->description(),
            'inputSchema' => $this->jsonSchema($this->inputSchema()),
            'annotations' => ['title' => $this->title(), ...$this->annotations()],
        ];
    }

    /**
     * @return array{readOnlyHint: bool, destructiveHint: bool, idempotentHint: bool, openWorldHint: bool}
     */
    protected function writes(bool $destructive, bool $idempotent): array
    {
        return ['readOnlyHint' => false, 'destructiveHint' => $destructive, 'idempotentHint' => $idempotent, 'openWorldHint' => false];
    }

    /**
     * @param  array<string, mixed>  $properties
     * @param  list<string>  $required
     * @return array<string, mixed>
     */
    protected function objectSchema(array $properties, array $required = []): array
    {
        $schema = ['type' => 'object', 'properties' => $properties];

        if ($required !== []) {
            $schema['required'] = $required;
        }

        $schema['additionalProperties'] = false;

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    protected function cursorProperty(): array
    {
        return ['type' => 'string', 'pattern' => '^[0-9]{1,18}$', 'description' => 'next_cursor from the previous page; omit for the first page.'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function limitProperty(int $default, int $max): array
    {
        return ['type' => 'integer', 'minimum' => 1, 'maximum' => $max, 'default' => $default, 'description' => "Page size (default {$default}, max {$max})."];
    }

    /**
     * Cuts text to at most $maxBytes without splitting a UTF-8 character.
     *
     * @return array{0: string, 1: bool} the text and whether it was cut
     */
    protected function clip(string $text, int $maxBytes): array
    {
        if (strlen($text) <= $maxBytes) {
            return [$text, false];
        }

        return [mb_strcut($text, 0, $maxBytes, 'UTF-8'), true];
    }

    /**
     * Empty `properties` must serialize as a JSON object, not an array.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function jsonSchema(array $schema): array
    {
        if (($schema['properties'] ?? null) === []) {
            $schema['properties'] = new stdClass;
        }

        foreach ($schema['properties'] ?? [] as $name => $property) {
            if (is_array($property) && ($property['type'] ?? null) === 'object') {
                $schema['properties'][$name] = $this->jsonSchema($property);
            }
        }

        return $schema;
    }
}
