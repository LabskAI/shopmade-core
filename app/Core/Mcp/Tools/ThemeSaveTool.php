<?php

namespace App\Core\Mcp\Tools;

use App\Core\Mcp\McpContext;

class ThemeSaveTool extends SiteTool
{
    public function name(): string
    {
        return 'theme_save';
    }

    public function title(): string
    {
        return 'Change theme tokens';
    }

    public function description(): string
    {
        return 'Set new values for tokens that theme/tokens.css already declares (see theme_get). Plain CSS values only: no ; { } < > \\ '
            .'url( expression( @import or comments. Live at once.';
    }

    public function inputSchema(): array
    {
        return $this->objectSchema([
            'tokens' => [
                'type' => 'array',
                'minItems' => 1,
                'maxItems' => 200,
                'items' => $this->objectSchema([
                    'name' => ['type' => 'string', 'pattern' => '^--[a-zA-Z0-9_-]+$', 'maxLength' => 100, 'description' => 'Custom property name, with the leading --.'],
                    'value' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200],
                ], ['name', 'value']),
            ],
        ], ['tokens']);
    }

    protected function writesSite(): bool
    {
        return true;
    }

    public function handle(array $arguments, McpContext $context): ToolResult
    {
        $values = array_column($arguments['tokens'], 'value', 'name');

        return new ToolResult($this->saved($this->editor->saveTheme($values, $context->actor)));
    }
}
