<?php

namespace Tests\Fixtures;

use App\Core\Mcp\McpContext;
use App\Core\Mcp\Tools\Tool;
use App\Core\Mcp\Tools\ToolResult;
use App\Enums\ConnectorScope;

/**
 * A package-style MCP tool for the Hooks::mcpTool() tests.
 */
class PingTool extends Tool
{
    public function name(): string
    {
        return 'demo_ping';
    }

    public function title(): string
    {
        return 'Ping';
    }

    public function description(): string
    {
        return 'Answers pong.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => (object) [], 'additionalProperties' => false];
    }

    public function scope(): ConnectorScope
    {
        return ConnectorScope::Operator;
    }

    public function handle(array $arguments, McpContext $context): ToolResult
    {
        return new ToolResult(['answer' => 'pong']);
    }
}
