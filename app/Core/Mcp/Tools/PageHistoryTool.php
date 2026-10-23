<?php

namespace App\Core\Mcp\Tools;

use App\Core\Mcp\McpContext;
use App\Models\FrontRevision;

class PageHistoryTool extends SiteTool
{
    public function name(): string
    {
        return 'page_history';
    }

    public function title(): string
    {
        return 'List the saved versions of a page';
    }

    public function description(): string
    {
        return 'The saved versions of one page version, newest first: who saved it, when, and how (dashboard or mcp). Pass a revision id '
            .'to page_restore to bring a version back.';
    }

    public function inputSchema(): array
    {
        return $this->objectSchema($this->pageProperties(), ['slug', 'lang']);
    }

    public function handle(array $arguments, McpContext $context): ToolResult
    {
        $history = $this->editor->history($this->editor->pageFile($arguments['slug'], $arguments['lang']));

        return new ToolResult([
            'versions' => array_map(fn (FrontRevision $revision): array => [
                'revision' => $revision->id,
                'note' => $revision->note,
                'by' => $revision->user?->name ?? $revision->actor,
                'via' => $revision->via,
                'saved_at' => $revision->created_at?->toIso8601String(),
                'deleted' => $revision->content === null,
            ], $history),
        ]);
    }
}
