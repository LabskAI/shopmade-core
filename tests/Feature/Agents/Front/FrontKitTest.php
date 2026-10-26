<?php

use App\Core\Front\FrontKit;
use Illuminate\Support\Facades\File;
use Tests\Feature\Agents\Front\FrontFixture;

afterEach(fn () => FrontFixture::cleanup());

it('reads the demo facts, languages and never-write list', function () {
    $kit = app(FrontKit::class);

    $facts = $kit->facts('demo', base_path());

    expect($facts['name'])->toBe('Demo Home Services')
        ->and($facts['cities'])->toBe(['Riverton', 'Oak Hollow', 'Pine Ridge'])
        ->and($facts['discovery'])->toBe(['ai_search' => true, 'ai_training' => false])
        ->and($kit->languages('demo', base_path()))->toBe(['en', 'es'])
        ->and($kit->neverWrite('demo', base_path()))->toContain('certified', 'OSHA', '5-star', '#1', 'licensed and insured')
        ->and($kit->factsYaml('demo', base_path()))->toContain('name: Demo Home Services');
});

it('lists the demo pages with their frontmatter and body', function () {
    $pages = app(FrontKit::class)->pages('demo', base_path());

    $home = collect($pages)->firstWhere('lang', 'es');

    expect(collect($pages)->map(fn (array $page): string => $page['slug'].'.'.$page['lang'])->all())
        ->toBe(['home.en', 'home.es', 'services.en', 'services.es'])
        ->and($home['path'])->toBe('fronts/demo/pages/home.es.md')
        ->and($home['frontmatter']['layout'])->toBe('home')
        ->and($home['frontmatter']['title'])->toContain('Limpieza')
        ->and($home['body'])->toStartWith('# ');
});

it('lists the demo skills with their descriptions and returns their content', function () {
    $kit = app(FrontKit::class);

    $skills = $kit->skills('demo', base_path());

    expect(array_column($skills, 'name'))->toBe(['brand-voice', 'design-direction', 'design-taste', 'no-claims'])
        ->and(array_filter(array_column($skills, 'description')))->toHaveCount(4)
        ->and($skills[3]['path'])->toBe('fronts/demo/skills/no-claims.md')
        ->and($kit->skill('demo', 'no-claims', base_path()))->toContain('name: no-claims');
});

it('returns nothing for names that try to leave the front folder', function (string $method, array $arguments) {
    $base = FrontFixture::create();
    File::put($base.'/secret.md', 'APP_KEY=base64:secret');

    expect(app(FrontKit::class)->{$method}(...$arguments))->toBeNull();
})->with([
    'skill traversal' => ['skill', ['acme', '../../secret']],
    'page slug traversal' => ['page', ['acme', '../../secret', 'en']],
    'page language traversal' => ['page', ['acme', 'home', '../en']],
    'read traversal' => ['read', ['acme', '../../secret.md']],
    'invalid company' => ['factsYaml', ['../acme']],
]);

it('ignores symlinked pages and skills', function () {
    $base = FrontFixture::create();
    File::put($base.'/secret.md', "---\ntitle: Leak\ndescription: Leak\n---\n\nAPP_KEY=base64:secret\n");
    symlink($base.'/secret.md', $base.'/fronts/acme/pages/leak.en.md');
    symlink($base.'/secret.md', $base.'/fronts/acme/skills/leak.md');

    $kit = app(FrontKit::class);

    expect($kit->page('acme', 'leak', 'en'))->toBeNull()
        ->and(array_column($kit->pages('acme'), 'slug'))->not->toContain('leak')
        ->and($kit->skill('acme', 'leak'))->toBeNull()
        ->and(array_column($kit->skills('acme'), 'name'))->toBe(['brand-voice']);
});

it('returns empty facts when facts.yaml is not valid YAML', function () {
    FrontFixture::create(['facts.yaml' => "name: [unclosed\n"]);

    $kit = app(FrontKit::class);

    expect($kit->facts('acme'))->toBe([])
        ->and($kit->languages('acme'))->toBe(['en'])
        ->and($kit->neverWrite('acme'))->toBe([]);
});
