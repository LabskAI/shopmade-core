<?php

use Illuminate\Support\Facades\Process;
use Symfony\Component\Yaml\Yaml;

require_once __DIR__.'/McpHelpers.php';
require_once __DIR__.'/../../Site/SiteHelpers.php';

beforeEach(function () {
    $this->front = siteSandbox();
});

it('lists the front-kit tools: the CSS read to operator, the writes to director', function () {
    [$bearer, $session] = mcpDirector(['operator']);
    $readOnly = collect(mcpPost(mcpMessage('tools/list'), $bearer, $session)->json('result.tools'))->pluck('name');

    [$bearer, $session] = mcpDirector();
    $tools = collect(mcpPost(mcpMessage('tools/list'), $bearer, $session)->json('result.tools'))->keyBy('name');

    expect($readOnly)->toContain('components_css_get')->not->toContain('components_css_save')->not->toContain('asset_put')
        ->and($tools->keys())->toContain('components_css_save', 'asset_put')
        ->and(array_keys($tools['page_save']['inputSchema']['properties']))->toContain('layout', 'hero', 'forms');
});

it('saves a page layout, hero and forms, and removes the hero with an empty object', function () {
    [$bearer, $session] = mcpDirector();

    mcpCall('page_save', [
        'slug' => 'services', 'lang' => 'en', 'title' => 'Services', 'description' => 'What we do.', 'body' => "# Services\n\n:::form slug=\"contact\"\n:::",
        'layout' => 'home', 'hero' => ['image' => 'assets/hero-art.svg', 'alt' => 'Art', 'position' => '50% 30%', 'style' => 'color: red'], 'forms' => ['contact'],
    ], $bearer, $session)->assertOk()->assertJsonPath('result.structuredContent.saved', true);

    $frontmatter = Yaml::parse(explode('---', file_get_contents($this->front.'/pages/services.en.md'))[1]);

    expect($frontmatter)->toMatchArray([
        'layout' => 'home',
        'hero' => ['image' => 'assets/hero-art.svg', 'alt' => 'Art', 'position' => '50% 30%'],
        'forms' => ['contact'],
        'nav_label' => 'Services',
    ]);

    mcpCall('page_save', ['slug' => 'services', 'lang' => 'en', 'title' => 'Services', 'description' => 'What we do.', 'body' => 'x', 'hero' => (object) [], 'forms' => []], $bearer, $session)->assertOk();

    expect(file_get_contents($this->front.'/pages/services.en.md'))->not->toContain('hero:')->not->toContain('forms:')->toContain('layout: home');
});

it('refuses a hero, layout or form the front cannot serve, writing nothing', function (array $fields, int $code) {
    [$bearer, $session] = mcpDirector();
    $before = file_get_contents($this->front.'/pages/services.en.md');

    mcpCall('page_save', ['slug' => 'services', 'lang' => 'en', 'title' => 'S', 'description' => 'D', 'body' => 'B', ...$fields], $bearer, $session)
        ->assertOk()
        ->assertJsonPath('error.code', $code);

    expect(file_get_contents($this->front.'/pages/services.en.md'))->toBe($before);
})->with([
    'missing image' => [['hero' => ['image' => 'assets/nope.svg']], -32004],
    'traversal' => [['hero' => ['image' => 'assets/../facts.yaml']], -32602],
    'remote image' => [['hero' => ['image' => 'https://evil.example/x.png']], -32602],
    'video as image' => [['hero' => ['image' => 'assets/motion.js']], -32602],
    'css in position' => [['hero' => ['image' => 'assets/hero-art.svg', 'position' => '50% 50%; color: red']], -32602],
    'unknown layout' => [['layout' => 'landing'], -32602],
    'undeclared form' => [['forms' => ['newsletter']], -32602],
]);

it('reads and replaces the component CSS, refusing remote urls and style breakouts', function () {
    [$bearer, $session] = mcpDirector();

    expect(mcpCall('components_css_get', [], $bearer, $session)->json('result.structuredContent.css'))->toContain('.front-card');

    mcpCall('components_css_save', ['css' => ".front-card { background: url('assets/hero-art.svg'); }"], $bearer, $session)
        ->assertJsonPath('result.structuredContent.saved', true);

    expect(file_get_contents($this->front.'/theme/components.css'))->toBe(".front-card { background: url('assets/hero-art.svg'); }\n")
        ->and(Process::path(dirname($this->front))->run(['git', 'log', '--format=%s', '-1'])->output())->toContain('theme/components.css');

    foreach (['.a { background: url(https://evil.example/x.png); }', '.a{} </style><script>x</script>', '@import "x.css";'] as $css) {
        mcpCall('components_css_save', ['css' => $css], $bearer, $session)->assertJsonPath('error.code', -32602);
    }

    expect(file_get_contents($this->front.'/theme/components.css'))->toBe(".front-card { background: url('assets/hero-art.svg'); }\n");
});

it('uploads an asset under assets/, commits it and returns its URL', function () {
    [$bearer, $session] = mcpDirector();
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 8 8"><circle cx="4" cy="4" r="4"/></svg>';

    $result = mcpCall('asset_put', ['path' => 'icons/dot.svg', 'content_base64' => base64_encode($svg)], $bearer, $session)
        ->assertOk()
        ->json('result.structuredContent');

    expect($result['saved'])->toBeTrue()
        ->and($result['path'])->toBe('fronts/demo/assets/icons/dot.svg')
        ->and($result['reference'])->toBe('assets/icons/dot.svg')
        ->and($result['url'])->toMatch('#/site/assets/icons/dot\.svg\?v=[0-9a-f]+$#')
        ->and(file_get_contents($this->front.'/assets/icons/dot.svg'))->toBe($svg)
        ->and(Process::path(dirname($this->front))->run(['git', 'log', '--format=%s', '-1'])->output())->toContain('Added assets/icons/dot.svg');

    $this->get('/site/assets/icons/dot.svg')->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
});

it('refuses scripts, unknown types, traversal, mismatched content and bad base64', function (string $path, string $content, int $code) {
    [$bearer, $session] = mcpDirector();

    mcpCall('asset_put', ['path' => $path, 'content_base64' => $content], $bearer, $session)
        ->assertOk()
        ->assertJsonPath('error.code', $code);

    expect(glob($this->front.'/assets/x.*'))->toBe([])
        ->and(file_exists(dirname($this->front).'/x.svg'))->toBeFalse();
})->with([
    'script' => ['x.js', base64_encode('alert(1)'), -32005],
    'html' => ['x.html', base64_encode('<script>alert(1)</script>'), -32005],
    'traversal' => ['../x.svg', base64_encode('<svg/>'), -32602],
    'html posing as svg' => ['x.svg', base64_encode('<html><script>alert(1)</script></html>'), -32602],
    'text posing as png' => ['x.png', base64_encode('not a png'), -32602],
    'bad base64' => ['x.svg', '%%%not-base64%%%', -32602],
]);
