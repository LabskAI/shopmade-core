<?php

namespace App\Core\Mcp\Tools;

use App\Core\Mcp\McpContext;

class PageRestoreTool extends SiteTool
{
    public function name(): string
    {
        return 'page_restore';
    }

    public function title(): string
    {
        return 'Restore a saved version of a page';
    }

    public function description(): string
    {
        return 'Make a saved version (a revision from page_history) the live page again. It becomes the newest version.';
    }

    public function inputSchema(): array
    {
        return $this->objectSchema([
            ...$this->pageProperties(),
            'revision' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Revision id from page_history.'],
        ], ['slug', 'lang', 'revision']);
    }

    protected function writesSite(): bool
    {
        return true;
    }

    public function handle(array $arguments, McpContext $context): ToolResult
    {
        $result = $this->editor->restore($this->editor->pageFile($arguments['slug'], $arguments['lang']), $arguments['revision'], $context->actor);

        return new ToolResult($this->saved($result, $this->editor->liveUrl($arguments['slug'], $arguments['lang'])));
    }
}
