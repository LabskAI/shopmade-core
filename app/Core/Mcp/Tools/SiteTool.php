<?php

namespace App\Core\Mcp\Tools;

use App\Core\Front\FrontEditor;
use App\Core\Front\FrontKit;
use App\Core\Front\FrontSite;
use App\Enums\ConnectorScope;

/**
 * A tool on the company website (fronts/<company>) through FrontEditor: reads need operator, writes need director.
 */
abstract class SiteTool extends Tool
{
    public function __construct(protected FrontEditor $editor) {}

    public function scope(): ConnectorScope
    {
        return $this->writesSite() ? ConnectorScope::Director : ConnectorScope::Operator;
    }

    public function annotations(): array
    {
        return $this->writesSite() ? $this->writes(destructive: $this->isDestructive(), idempotent: true) : parent::annotations();
    }

    protected function writesSite(): bool
    {
        return false;
    }

    protected function isDestructive(): bool
    {
        return false;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function pageProperties(): array
    {
        return [
            'slug' => ['type' => 'string', 'pattern' => '^'.FrontKit::SLUG_PATTERN.'$', 'maxLength' => 80, 'description' => 'Page slug: lowercase letters, digits and dashes (home is the home page).'],
            'lang' => ['type' => 'string', 'pattern' => '^'.FrontKit::LANGUAGE_PATTERN.'$', 'description' => 'Two-letter language code of the version, one of the site languages.'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function pageFieldProperties(): array
    {
        return [
            'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200, 'description' => 'Page title (the <title> and the main heading).'],
            'description' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 500, 'description' => 'Meta description: one or two sentences.'],
            'body' => ['type' => 'string', 'maxLength' => 100000, 'description' => 'The page content in markdown, without frontmatter.'],
            'nav_label' => ['type' => 'string', 'maxLength' => 80, 'description' => 'Short label in the navigation; empty removes it.'],
            'cities' => ['type' => 'array', 'items' => ['type' => 'string', 'maxLength' => 100], 'maxItems' => 100, 'description' => 'Cities this page is about (service-area pages).'],
            'layout' => ['type' => 'string', 'enum' => FrontSite::LAYOUTS, 'description' => 'home (hero beside the intro) or page. Keep every language version on the same layout.'],
            'hero' => $this->objectSchema([
                'image' => ['type' => 'string', 'maxLength' => 300, 'description' => 'Image under assets/ (svg, png, jpg, webp, avif, gif), e.g. assets/hero-art.svg.'],
                'alt' => ['type' => 'string', 'maxLength' => 300, 'description' => 'Alt text; empty for a decorative image.'],
                'kicker' => ['type' => 'string', 'maxLength' => 300, 'description' => 'Short line above the headline.'],
                'caption' => ['type' => 'string', 'maxLength' => 300],
                'position' => ['type' => 'string', 'pattern' => '^\\d{1,3}% \\d{1,3}%$', 'description' => 'Image focus, e.g. "50% 30%".'],
                'poster' => ['type' => 'string', 'maxLength' => 300, 'description' => 'Poster image under assets/ for a hero video.'],
                'mp4' => ['type' => 'string', 'maxLength' => 300, 'description' => 'Looping muted video under assets/ (.mp4).'],
                'webm' => ['type' => 'string', 'maxLength' => 300, 'description' => 'Looping muted video under assets/ (.webm).'],
            ]) + ['description' => 'Hero media from the page frontmatter. Files must exist under assets/ (asset_put). An empty object removes the hero.'],
            'forms' => ['type' => 'array', 'maxItems' => 10, 'items' => ['type' => 'string', 'pattern' => '^'.FrontKit::SLUG_PATTERN.'$'], 'description' => 'Form slugs from facts.forms shown after the page (an inline :::form slug="…" fence places one inside the body instead). An empty list removes them.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{title: string, description: string, body?: string, nav_label?: string, cities?: list<string>, layout?: string, hero?: array<string, string>, forms?: list<string>}
     */
    protected function pageFields(array $arguments): array
    {
        return array_intersect_key($arguments, array_flip(['title', 'description', 'body', 'nav_label', 'cities', 'layout', 'hero', 'forms']));
    }

    /**
     * @param  array{path: string, saved: bool, lint?: array{ok: bool, errors: list<array<string, string>>, warnings: list<array<string, string>>}}  $result
     * @return array<string, mixed>
     */
    protected function saved(array $result, ?string $url = null): array
    {
        return array_filter([
            'saved' => $result['saved'],
            'path' => $result['path'],
            'live_url' => $url,
            'lint' => $result['lint'] ?? null,
        ], fn (mixed $value): bool => $value !== null);
    }
}
