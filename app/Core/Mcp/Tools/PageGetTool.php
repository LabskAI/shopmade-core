<?php

namespace App\Core\Mcp\Tools;

use App\Core\Mcp\McpContext;
use App\Core\Mcp\McpError;

class PageGetTool extends SiteTool
{
    public function name(): string
    {
        return 'page_get';
    }

    public function title(): string
    {
        return 'Read a page';
    }

    public function description(): string
    {
        return 'One page version: its frontmatter (title, description, layout and the rest), its markdown body, its lint results and live URL.';
    }

    public function inputSchema(): array
    {
        return $this->objectSchema($this->pageProperties(), ['slug', 'lang']);
    }

    public function handle(array $arguments, McpContext $context): ToolResult
    {
        $page = $this->editor->page($arguments['slug'], $arguments['lang'])
            ?? throw McpError::notFound("There is no page {$arguments['slug']} ({$arguments['lang']}).", 'Call pages_list for the pages, or create it with page_create.');

        return new ToolResult([
            'slug' => $page['slug'],
            'lang' => $page['lang'],
            'frontmatter' => $page['frontmatter'],
            'body' => $page['body'],
            'live_url' => $this->editor->liveUrl($page['slug'], $page['lang']),
            'lint' => $this->editor->lintFor($this->editor->lint(), $this->editor->repoPath($this->editor->pageFile($page['slug'], $page['lang']))),
        ]);
    }
}
