<?php

namespace App\Core\Mcp\Tools;

use App\Core\Mcp\McpContext;

class PageDeleteTool extends SiteTool
{
    public function name(): string
    {
        return 'page_delete';
    }

    public function title(): string
    {
        return 'Delete a page';
    }

    public function description(): string
    {
        return 'Delete one language version of a page from the live site. The home page cannot be deleted. Its last version stays '
            .'in page_history and page_restore brings it back.';
    }

    public function inputSchema(): array
    {
        return $this->objectSchema($this->pageProperties(), ['slug', 'lang']);
    }

    protected function writesSite(): bool
    {
        return true;
    }

    protected function isDestructive(): bool
    {
        return true;
    }

    public function handle(array $arguments, McpContext $context): ToolResult
    {
        return new ToolResult($this->saved($this->editor->deletePage($arguments['slug'], $arguments['lang'], $context->actor)));
    }
}
