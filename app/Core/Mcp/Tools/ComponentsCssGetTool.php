<?php

namespace App\Core\Mcp\Tools;

use App\Core\Front\FrontComponents;
use App\Core\Mcp\McpContext;

class ComponentsCssGetTool extends SiteTool
{
    public function name(): string
    {
        return 'components_css_get';
    }

    public function title(): string
    {
        return 'Read the component CSS';
    }

    public function description(): string
    {
        return 'The brand stylesheet for the kit components (theme/components.css), and the class contract it styles: '
            .'front-<name> on each component, front-<name>--<variant> for variant="…", front-<name>__<part> for its parts, plus class="…" tokens.';
    }

    public function inputSchema(): array
    {
        return $this->objectSchema([]);
    }

    public function handle(array $arguments, McpContext $context): ToolResult
    {
        return new ToolResult([
            'css' => $this->editor->componentsCss() ?? '',
            'exists' => $this->editor->componentsCss() !== null,
            'components' => array_values(array_unique(FrontComponents::NAMES)),
        ]);
    }
}
