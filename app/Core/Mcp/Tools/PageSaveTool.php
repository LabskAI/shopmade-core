<?php

namespace App\Core\Mcp\Tools;

use App\Core\Mcp\McpContext;

class PageSaveTool extends SiteTool
{
    public function name(): string
    {
        return 'page_save';
    }

    public function title(): string
    {
        return 'Edit a page';
    }

    public function description(): string
    {
        return "Replace a page's title, description and body (and optionally its navigation label and cities). Other frontmatter "
            .'keys (layout, forms, jsonld) are kept. Omitting body empties the page, so always send the full body. Live at once.';
    }

    public function inputSchema(): array
    {
        return $this->objectSchema([...$this->pageProperties(), ...$this->pageFieldProperties()], ['slug', 'lang', 'title', 'description', 'body']);
    }

    protected function writesSite(): bool
    {
        return true;
    }

    public function handle(array $arguments, McpContext $context): ToolResult
    {
        $result = $this->editor->savePage($arguments['slug'], $arguments['lang'], $this->pageFields($arguments), $context->actor);

        return new ToolResult($this->saved($result, $this->editor->liveUrl($arguments['slug'], $arguments['lang'])));
    }
}
