<?php

use App\Core\Packages\Hooks;
use App\Models\FrontRevision;
use App\Models\User;
use Tests\Fixtures\PingTool;

require_once __DIR__.'/McpHelpers.php';
require_once __DIR__.'/../../Site/SiteHelpers.php';

beforeEach(function () {
    $this->front = siteSandbox();
});

it('lists the site tools, reads to operator and writes to director', function () {
    [$bearer, $session] = mcpDirector(['operator']);

    $readOnly = collect(mcpPost(mcpMessage('tools/list'), $bearer, $session)->assertOk()->json('result.tools'))->pluck('name')->all();

    [$bearer, $session] = mcpDirector(['director']);

    $tools = collect(mcpPost(mcpMessage('tools/list'), $bearer, $session)->json('result.tools'))->keyBy('name');

    // Installed packages add their tools after these, so only the leading core tools are pinned.
    expect(array_slice($readOnly, 0, 6))->toBe(['facts_get', 'pages_list', 'page_get', 'page_history', 'theme_get', 'site_lint'])
        ->and($readOnly)->not->toContain('page_save')
        ->and($tools->keys()->take(12)->all())->toBe([
            'facts_get', 'facts_save', 'pages_list', 'page_get', 'page_create', 'page_save', 'page_delete',
            'page_history', 'page_restore', 'theme_get', 'theme_save', 'site_lint',
        ])
        ->and($tools['page_save']['inputSchema']['required'])->toBe(['slug', 'lang', 'title', 'description', 'body'])
        ->and($tools['page_get']['annotations'])->toMatchArray(['readOnlyHint' => true])
        ->and($tools['page_delete']['annotations'])->toMatchArray(['readOnlyHint' => false, 'destructiveHint' => true]);
});

it('refuses a write tool to a read-only token', function () {
    [$bearer, $session] = mcpDirector(['operator']);

    mcpCall('page_save', ['slug' => 'home', 'lang' => 'en', 'title' => 'A', 'description' => 'B', 'body' => 'C'], $bearer, $session)
        ->assertOk()
        ->assertJsonPath('error.code', -32002);
});

it('reads pages and saves one live, recorded as an MCP revision', function () {
    [$bearer, $session, $token] = mcpDirector();

    $pages = mcpCall('pages_list', [], $bearer, $session)->assertOk()->json('result.structuredContent.pages');
    $page = mcpCall('page_get', ['slug' => 'services', 'lang' => 'en'], $bearer, $session)->json('result.structuredContent');

    expect(collect($pages)->pluck('slug')->unique()->values()->all())->toBe(['home', 'services'])
        ->and($page['frontmatter'])->toHaveKey('title');

    $saved = mcpCall('page_save', [
        'slug' => 'services', 'lang' => 'en', 'title' => 'Services', 'description' => 'What we do.', 'body' => 'Written by Claude.',
    ], $bearer, $session)->assertOk()->json('result.structuredContent');

    $revision = FrontRevision::query()->latest('id')->first();

    expect($saved['saved'])->toBeTrue()
        ->and($saved['lint'])->toHaveKey('errors')
        ->and(file_get_contents($this->front.'/pages/services.en.md'))->toContain('Written by Claude.')
        ->and($revision->via)->toBe('mcp')
        ->and($revision->user_id)->toBe($token->user_id);

    $history = mcpCall('page_history', ['slug' => 'services', 'lang' => 'en'], $bearer, $session)->json('result.structuredContent.versions');

    expect($history)->toHaveCount(2);

    mcpCall('page_restore', ['slug' => 'services', 'lang' => 'en', 'revision' => $history[1]['revision']], $bearer, $session)->assertOk();

    expect(file_get_contents($this->front.'/pages/services.en.md'))->not->toContain('Written by Claude.');
});

it('returns a clean error for a page that does not exist and for the home page delete', function () {
    [$bearer, $session] = mcpDirector();

    mcpCall('page_get', ['slug' => 'nope', 'lang' => 'en'], $bearer, $session)->assertJsonPath('error.code', -32004);
    mcpCall('page_delete', ['slug' => 'home', 'lang' => 'en'], $bearer, $session)->assertJsonPath('error.code', -32602);
});

it('saves facts and theme tokens', function () {
    [$bearer, $session] = mcpDirector();
    $token = array_key_first(mcpCall('theme_get', [], $bearer, $session)->json('result.structuredContent.tokens'));

    mcpCall('facts_save', ['phone' => '+1 239 555 0100'], $bearer, $session)->assertJsonPath('result.structuredContent.saved', true);
    mcpCall('theme_save', ['tokens' => [['name' => $token, 'value' => '#123456']]], $bearer, $session)->assertJsonPath('result.structuredContent.saved', true);
    mcpCall('theme_save', ['tokens' => [['name' => $token, 'value' => 'url(evil)']]], $bearer, $session)->assertJsonPath('error.code', -32602);
    mcpCall('facts_save', ['email' => 'not-an-email'], $bearer, $session)->assertJsonPath('error.code', -32602);
    mcpCall('facts_save', ['phone' => 'call me maybe'], $bearer, $session)->assertJsonPath('error.code', -32602);

    expect(mcpCall('facts_get', [], $bearer, $session)->json('result.structuredContent.facts.phone'))->toBe('+1 239 555 0100');
});

it('refuses tools once the connector user can no longer edit the website', function () {
    [$token, $bearer] = mcpToken(['director'], User::factory()->admin()->create());
    $session = mcpSession($bearer);
    $token->user->update(['role' => 'user']);

    mcpCall('pages_list', [], $bearer, $session)->assertJsonPath('error.code', -32002);
});

it('lists and reads the facts, theme, page and skill resources and the edit-site prompt', function () {
    [$bearer, $session] = mcpDirector();

    $uris = collect(mcpPost(mcpMessage('resources/list'), $bearer, $session)->json('result.resources'))->pluck('uri');
    $page = mcpPost(mcpMessage('resources/read', ['uri' => 'shopmade://demo/pages/en/home']), $bearer, $session)->json('result.contents.0');
    $skill = mcpPost(mcpMessage('resources/read', ['uri' => 'shopmade://demo/skills/no-claims']), $bearer, $session)->json('result.contents.0.text');
    $prompt = mcpPost(mcpMessage('prompts/get', ['name' => 'edit-site']), $bearer, $session)->json('result.messages.0.content.text');

    expect($uris)->toContain('shopmade://demo/facts', 'shopmade://demo/theme', 'shopmade://demo/pages/en/home', 'shopmade://demo/skills/no-claims')
        ->and($page['mimeType'])->toBe('text/markdown')
        ->and($page['text'])->toContain('title:')
        ->and($skill)->toContain('name: no-claims')
        ->and($prompt)->toContain('Pages: home (en)', 'shopmade://demo/skills/brand-voice');

    mcpPost(mcpMessage('resources/read', ['uri' => 'shopmade://other/facts']), $bearer, $session)->assertJsonPath('error.code', -32004);
});

it('lists and calls a tool that a package registers, after the site tools', function () {
    app(Hooks::class)->mcpTool('demo_ping', PingTool::class);
    app(Hooks::class)->mcpTool('page_get', PingTool::class);

    [$bearer, $session] = mcpDirector(['operator']);

    $names = collect(mcpPost(mcpMessage('tools/list'), $bearer, $session)->json('result.tools'))->pluck('name')->all();

    expect(array_slice($names, 0, 6))->toBe(['facts_get', 'pages_list', 'page_get', 'page_history', 'theme_get', 'site_lint'])
        ->and(array_count_values($names)['page_get'])->toBe(1)
        ->and(end($names))->toBe('demo_ping');

    mcpCall('demo_ping', [], $bearer, $session)
        ->assertOk()
        ->assertJsonPath('result.structuredContent.answer', 'pong');
});
