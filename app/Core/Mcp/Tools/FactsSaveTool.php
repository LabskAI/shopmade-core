<?php

namespace App\Core\Mcp\Tools;

use App\Core\Front\FrontKit;
use App\Core\Mcp\McpContext;

class FactsSaveTool extends SiteTool
{
    public function name(): string
    {
        return 'facts_save';
    }

    public function title(): string
    {
        return 'Change the company facts';
    }

    public function description(): string
    {
        return 'Change facts.yaml: pass only the facts to change. Lists replace the whole list. An empty phone or email removes it. '
            .'Keys not listed here are kept. Live at once; the old version stays in the history.';
    }

    public function inputSchema(): array
    {
        $lines = fn (string $description): array => ['type' => 'array', 'items' => ['type' => 'string', 'maxLength' => 500], 'maxItems' => 200, 'description' => $description];

        return [...$this->objectSchema([
            'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200, 'description' => 'Business name.'],
            'phone' => ['type' => 'string', 'maxLength' => 50],
            'email' => ['type' => 'string', 'maxLength' => 200],
            'languages' => ['type' => 'array', 'items' => ['type' => 'string', 'pattern' => '^'.FrontKit::LANGUAGE_PATTERN.'$'], 'minItems' => 1, 'maxItems' => 10, 'uniqueItems' => true, 'description' => 'Site languages, the default first.'],
            'cities' => $lines('Cities served.'),
            'hours' => $lines('Opening hours, one line each.'),
            'never_write' => $lines('Phrases the site must never use.'),
            'services' => [
                'type' => 'array',
                'maxItems' => 100,
                'items' => $this->objectSchema([
                    'slug' => ['type' => 'string', 'pattern' => '^'.FrontKit::SLUG_PATTERN.'$', 'maxLength' => 80],
                    'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200],
                ], ['slug', 'name']),
                'description' => 'Services offered (name in the default language).',
            ],
            'discovery' => $this->objectSchema([
                'ai_search' => ['type' => 'boolean', 'description' => 'Allow AI search and assistants to read the site.'],
                'ai_training' => ['type' => 'boolean', 'description' => 'Allow AI training crawlers.'],
            ]),
        ]), 'minProperties' => 1];
    }

    protected function writesSite(): bool
    {
        return true;
    }

    public function handle(array $arguments, McpContext $context): ToolResult
    {
        return new ToolResult($this->saved($this->editor->saveFacts($arguments, $context->actor)));
    }
}
