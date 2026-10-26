<?php

namespace App\Core\Mcp\Tools;

use App\Core\Mcp\McpContext;

class PageCreateTool extends SiteTool
{
    public function name(): string
    {
        return 'page_create';
    }

    public function title(): string
    {
        return 'Create a page';
    }

    public function description(): string
    {
        return 'Create a new page version in one of the site languages. A new language version of an existing page copies its layout and forms '
            .'unless given. The body may use the kit components (see page_save). The page is live at once.';
    }

    public function inputSchema(): array
    {
        return $this->objectSchema([...$this->pageProperties(), ...$this->pageFieldProperties()], ['slug', 'lang', 'title', 'description']);
    }

    protected function writesSite(): bool
    {
        return true;
    }

    public function handle(array $arguments, McpContext $context): ToolResult
    {
        $result = $this->editor->createPage($arguments['slug'], $arguments['lang'], $this->pageFields($arguments), $context->actor);

        return new ToolResult($this->saved($result, $this->editor->liveUrl($arguments['slug'], $arguments['lang'])));
    }
}
