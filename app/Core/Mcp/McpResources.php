<?php

namespace App\Core\Mcp;

use App\Core\Front\FrontEditor;
use App\Core\Front\FrontKit;

/**
 * Read-only mirrors of the company's facts, theme, pages and writing skills under shopmade://<company>/…
 */
class McpResources
{
    public function __construct(private FrontKit $front, private FrontEditor $editor) {}

    /**
     * @return list<array<string, string>>
     */
    public function list(): array
    {
        $company = $this->editor->company();
        $resources = [];

        if ($this->front->factsYaml($company) !== null) {
            $resources[] = [
                'uri' => $this->base().'/facts',
                'name' => 'facts',
                'title' => "{$company} facts (facts.yaml)",
                'description' => 'The company facts: contact, services, languages and the never_write phrases.',
                'mimeType' => 'text/yaml',
            ];
        }

        if ($this->front->theme($company) !== null) {
            $resources[] = [
                'uri' => $this->base().'/theme',
                'name' => 'theme',
                'title' => "{$company} theme (theme/tokens.css)",
                'description' => 'The theme tokens of the site as CSS custom properties.',
                'mimeType' => 'text/css',
            ];
        }

        foreach ($this->editor->pages() as $page) {
            $resources[] = [
                'uri' => $this->base()."/pages/{$page['lang']}/{$page['slug']}",
                'name' => "page-{$page['slug']}-{$page['lang']}",
                'title' => 'Page: '.(is_string($page['frontmatter']['title'] ?? null) ? $page['frontmatter']['title'] : $page['slug'])." ({$page['lang']})",
                'description' => "The {$page['slug']} page in {$page['lang']}: markdown with frontmatter.",
                'mimeType' => 'text/markdown',
            ];
        }

        foreach ($this->front->skills($company) as $skill) {
            $resources[] = [
                'uri' => $this->base()."/skills/{$skill['name']}",
                'name' => "skill-{$skill['name']}",
                'title' => "Skill: {$skill['name']}",
                'description' => $skill['description'] !== '' ? $skill['description'] : 'House rules for writing and designing this site.',
                'mimeType' => 'text/markdown',
            ];
        }

        return $resources;
    }

    /**
     * @return list<array<string, string>>
     */
    public function templates(): array
    {
        return [[
            'uriTemplate' => $this->base().'/pages/{lang}/{slug}',
            'name' => 'page',
            'title' => 'Website page',
            'description' => 'One page (markdown with frontmatter) by language and slug.',
            'mimeType' => 'text/markdown',
        ]];
    }

    /**
     * @return list<array{uri: string, mimeType: string, text: string}>
     *
     * @throws McpError
     */
    public function read(string $uri): array
    {
        if (preg_match('#^shopmade://([^/]+)/(.+)$#', $uri, $match) !== 1) {
            throw McpError::invalidParams("Not a Shopmade resource URI: {$uri}.", 'Use a URI from resources/list or resources/templates/list.');
        }

        $company = $this->editor->company();

        if ($match[1] !== $company) {
            throw McpError::notFound("Resource not found: {$uri}.", "This server only serves company {$company} (shopmade://{$company}/…).");
        }

        $path = $match[2];

        return match (true) {
            $path === 'facts' => $this->text($uri, 'text/yaml', $this->front->factsYaml($company)),
            $path === 'theme' => $this->text($uri, 'text/css', $this->front->theme($company)),
            preg_match('#^pages/('.FrontKit::LANGUAGE_PATTERN.')/('.FrontKit::SLUG_PATTERN.')$#', $path, $page) === 1 => $this->text(
                $uri,
                'text/markdown',
                $this->front->read($company, $this->editor->pageFile($page[2], $page[1])),
            ),
            preg_match('#^skills/([a-z0-9][a-z0-9_-]{0,63})$#', $path, $skill) === 1 => $this->text(
                $uri,
                'text/markdown',
                $this->front->skill($company, $skill[1]),
            ),
            default => throw $this->missing($uri),
        };
    }

    /**
     * @return list<array{uri: string, mimeType: string, text: string}>
     */
    private function text(string $uri, string $mimeType, ?string $text): array
    {
        return [['uri' => $uri, 'mimeType' => $mimeType, 'text' => $text ?? throw $this->missing($uri)]];
    }

    private function base(): string
    {
        return 'shopmade://'.$this->editor->company();
    }

    private function missing(string $uri): McpError
    {
        return McpError::notFound("Resource not found: {$uri}.", 'Use a URI from resources/list or resources/templates/list.');
    }
}
