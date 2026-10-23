<?php

namespace App\Core\Mcp\Tools;

use App\Core\Front\FrontEditor;
use App\Core\Front\FrontKit;
use App\Core\Mcp\McpContext;
use App\Core\Mcp\McpError;

class FactsGetTool extends SiteTool
{
    public function __construct(FrontEditor $editor, private FrontKit $front)
    {
        parent::__construct($editor);
    }

    public function name(): string
    {
        return 'facts_get';
    }

    public function title(): string
    {
        return 'Read the company facts';
    }

    public function description(): string
    {
        return "Read the company's facts.yaml as data: name, contact, services, areas, languages, hours and the never_write phrases. "
            .'Never write anything listed in never_write on a page.';
    }

    public function inputSchema(): array
    {
        return $this->objectSchema([]);
    }

    public function handle(array $arguments, McpContext $context): ToolResult
    {
        $company = $this->editor->company();
        if ($this->front->factsYaml($company) === null) {
            throw McpError::notFound("No facts.yaml for company {$company} yet.", 'Save the facts with facts_save to create it.');
        }

        return new ToolResult([
            'company' => $company,
            'facts' => $this->editor->facts(),
            'never_write' => $this->front->neverWrite($company),
            'languages' => $this->editor->languages(),
        ]);
    }
}
