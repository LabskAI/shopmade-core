<?php

namespace App\Core\Mcp\Tools;

use App\Core\Mcp\McpContext;
use App\Core\Mcp\McpError;

class ThemeGetTool extends SiteTool
{
    public function name(): string
    {
        return 'theme_get';
    }

    public function title(): string
    {
        return 'Read the theme tokens';
    }

    public function description(): string
    {
        return 'The design tokens of the site (theme/tokens.css): CSS custom properties such as colors, fonts, type scale and spacing.';
    }

    public function inputSchema(): array
    {
        return $this->objectSchema([]);
    }

    public function handle(array $arguments, McpContext $context): ToolResult
    {
        $tokens = $this->editor->themeTokens() ?? throw McpError::notFound('This site has no theme/tokens.css.', 'The site uses the default theme.');

        return new ToolResult(['tokens' => $tokens]);
    }
}
