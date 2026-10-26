<?php

namespace App\Core\Mcp\Tools;

use App\Core\Front\FrontComponentsCss;
use App\Core\Mcp\McpContext;

class ComponentsCssSaveTool extends SiteTool
{
    public function name(): string
    {
        return 'components_css_save';
    }

    public function title(): string
    {
        return 'Replace the component CSS';
    }

    public function description(): string
    {
        return 'Replace theme/components.css (see components_css_get) with the full stylesheet. Refused when it contains < , backslash '
            .'escapes, @import, @charset, expression(, javascript:, or a url() that is not a file under assets/ (url("assets/…")). Live at once.';
    }

    public function inputSchema(): array
    {
        return $this->objectSchema([
            'css' => ['type' => 'string', 'maxLength' => FrontComponentsCss::MAX_BYTES, 'description' => 'The whole stylesheet.'],
        ], ['css']);
    }

    protected function writesSite(): bool
    {
        return true;
    }

    public function handle(array $arguments, McpContext $context): ToolResult
    {
        return new ToolResult($this->saved($this->editor->saveComponentsCss($arguments['css'], $context->actor)));
    }
}
