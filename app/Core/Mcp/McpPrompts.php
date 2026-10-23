<?php

namespace App\Core\Mcp;

use App\Core\Front\FrontEditor;
use App\Core\Front\FrontKit;

/**
 * Prompt templates for a Claude session editing the site. One prompt: edit-site.
 */
class McpPrompts
{
    public const EditSite = 'edit-site';

    public function __construct(private FrontKit $front, private FrontEditor $editor) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        return [[
            'name' => self::EditSite,
            'title' => 'Edit the Shopmade website',
            'description' => 'The pages, languages, never-write phrases and how edits go live. Load it before editing the site.',
            'arguments' => [],
        ]];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{description: string, messages: list<array{role: string, content: array{type: string, text: string}}>}
     *
     * @throws McpError
     */
    public function get(string $name, array $arguments): array
    {
        if ($name !== self::EditSite) {
            throw McpError::invalidParams("Unknown prompt: {$name}.", 'Call prompts/list; the only prompt is edit-site.');
        }

        return [
            'description' => 'Editing the Shopmade website of '.$this->editor->company(),
            'messages' => [[
                'role' => 'user',
                'content' => ['type' => 'text', 'text' => $this->text()],
            ]],
        ];
    }

    private function text(): string
    {
        $company = $this->editor->company();
        $pages = array_map(fn (array $page): string => "{$page['slug']} ({$page['lang']})", $this->editor->pages());
        $neverWrite = $this->front->neverWrite($company);
        $skills = array_map(fn (array $skill): string => "shopmade://{$company}/skills/{$skill['name']}", $this->front->skills($company));

        return implode("\n", [
            "You are editing the public website of {$company} through the Shopmade MCP server.",
            'Languages: '.implode(', ', $this->editor->languages()).' (the first is the default).',
            'Pages: '.($pages === [] ? 'none yet' : implode(', ', $pages)).'.',
            'Never write: '.($neverWrite === [] ? 'nothing listed' : implode('; ', $neverWrite)).'.',
            'House skills (read them before writing): '.($skills === [] ? 'none' : implode(', ', $skills)).'.',
            '',
            'How edits work:',
            '- Every save is live at once and kept as a revision; page_history lists them and page_restore brings one back.',
            '- A page is markdown with a title and a description. Keep every language version of a page in parity.',
            '- Facts (facts_save) feed the navigation, the footer and the AI-discovery files: change a phone number there, not on a page.',
            '- Each write returns the lint report of the whole front. Fix any error it lists before you finish.',
        ]);
    }
}
