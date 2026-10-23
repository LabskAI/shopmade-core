<?php

namespace App\Core\Mcp\Tools;

use App\Core\Mcp\McpContext;

class PagesListTool extends SiteTool
{
    public function name(): string
    {
        return 'pages_list';
    }

    public function title(): string
    {
        return 'List the website pages';
    }

    public function description(): string
    {
        return 'Every page of the site, one entry per language version, with its title, description and live URL.';
    }

    public function inputSchema(): array
    {
        return $this->objectSchema([]);
    }

    public function handle(array $arguments, McpContext $context): ToolResult
    {
        return new ToolResult([
            'languages' => $this->editor->languages(),
            'pages' => array_map(fn (array $page): array => [
                'slug' => $page['slug'],
                'lang' => $page['lang'],
                'title' => $page['frontmatter']['title'] ?? null,
                'description' => $page['frontmatter']['description'] ?? null,
                'live_url' => $this->editor->liveUrl($page['slug'], $page['lang']),
            ], $this->editor->pages()),
        ]);
    }
}
