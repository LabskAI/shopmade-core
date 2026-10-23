<?php

namespace App\Core\Mcp\Tools;

use App\Core\Mcp\McpContext;

class SiteLintTool extends SiteTool
{
    public function name(): string
    {
        return 'site_lint';
    }

    public function title(): string
    {
        return 'Check the website';
    }

    public function description(): string
    {
        return 'Run the front linter on the whole site: missing titles or descriptions, broken links, never_write phrases, '
            .'language parity and the other front:lint rules. Errors first.';
    }

    public function inputSchema(): array
    {
        return $this->objectSchema([]);
    }

    public function handle(array $arguments, McpContext $context): ToolResult
    {
        return new ToolResult($this->editor->lint());
    }
}
