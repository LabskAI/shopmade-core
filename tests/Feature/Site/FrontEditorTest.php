<?php

use App\Core\Agents\Actor;
use App\Core\Agents\AgentRefusedException;
use App\Core\Front\FrontEditor;
use App\Core\Front\FrontKit;
use App\Models\FrontRevision;
use App\Models\User;

require_once __DIR__.'/SiteHelpers.php';

beforeEach(function () {
    $this->front = siteSandbox();
    $this->editor = app(FrontEditor::class);
    $this->actor = Actor::forUser(User::factory()->admin()->create());
});

it('writes a page edit to the live file and keeps the original and the new version', function () {
    $original = file_get_contents($this->front.'/pages/services.en.md');

    $result = $this->editor->savePage('services', 'en', ['title' => 'Our services', 'description' => 'What we do.', 'body' => 'New body.'], $this->actor);

    expect($result['saved'])->toBeTrue()
        ->and($result['path'])->toBe('fronts/demo/pages/services.en.md')
        ->and($result['lint'])->toHaveKeys(['ok', 'errors', 'warnings'])
        ->and(file_get_contents($this->front.'/pages/services.en.md'))->toContain('title: \'Our services\'', 'New body.');

    $history = $this->editor->history('pages/services.en.md');

    expect($history)->toHaveCount(2)
        ->and($history[0]->note)->toBe('Edited')
        ->and($history[0]->actor)->toBe($this->actor->label())
        ->and($history[1]->content)->toBe($original);
});

it('saves nothing when the content did not change', function () {
    $fields = ['title' => 'Our services', 'description' => 'What we do.', 'body' => 'Same body.'];
    $this->editor->savePage('services', 'en', $fields, $this->actor);
    $revisions = FrontRevision::query()->count();

    expect($this->editor->savePage('services', 'en', $fields, $this->actor)['saved'])->toBeFalse()
        ->and(FrontRevision::query()->count())->toBe($revisions);
});

it('restores a saved version as a new live version', function () {
    $original = file_get_contents($this->front.'/pages/services.en.md');
    $this->editor->savePage('services', 'en', ['title' => 'Changed', 'description' => 'Changed.', 'body' => 'Changed.'], $this->actor);
    $first = collect($this->editor->history('pages/services.en.md'))->last();

    $this->editor->restore('pages/services.en.md', $first->id, $this->actor);

    expect(file_get_contents($this->front.'/pages/services.en.md'))->toBe($original)
        ->and($this->editor->history('pages/services.en.md')[0]->note)->toBe("Restored version #{$first->id}");
});

it('creates and deletes a page, keeping the deleted content in the history', function () {
    $this->editor->createPage('about', 'en', ['title' => 'About', 'description' => 'Who we are.', 'body' => 'Hello.'], $this->actor);

    expect(is_file($this->front.'/pages/about.en.md'))->toBeTrue();

    $this->editor->deletePage('about', 'en', $this->actor);

    expect(is_file($this->front.'/pages/about.en.md'))->toBeFalse()
        ->and(collect($this->editor->history('pages/about.en.md'))->pluck('note')->all())->toBe(['Deleted', 'Created']);
});

it('refuses to delete the home page, to create a page in a language the site lacks and to edit a file outside the front', function (Closure $call, string $reason) {
    expect(fn () => $call($this->editor, $this->actor))->toThrow(AgentRefusedException::class);

    try {
        $call($this->editor, $this->actor);
    } catch (AgentRefusedException $exception) {
        expect($exception->reason)->toBe($reason);
    }
})->with([
    'home' => [fn (FrontEditor $editor, Actor $actor) => $editor->deletePage('home', 'en', $actor), 'invalid_params'],
    'language' => [fn (FrontEditor $editor, Actor $actor) => $editor->createPage('about', 'fr', ['title' => 'A', 'description' => 'B'], $actor), 'invalid_params'],
    'path' => [fn (FrontEditor $editor, Actor $actor) => $editor->restore('../.env', 1, $actor), 'path_refused'],
]);

it('refuses a symlinked page instead of writing through it', function () {
    $outside = dirname($this->front, 2).'/outside.md';
    file_put_contents($outside, "---\ntitle: X\ndescription: Y\n---\nZ");
    unlink($this->front.'/pages/services.en.md');
    symlink($outside, $this->front.'/pages/services.en.md');

    expect(fn () => $this->editor->savePage('services', 'en', ['title' => 'A', 'description' => 'B', 'body' => 'C'], $this->actor))
        ->toThrow(AgentRefusedException::class);
});

it('refuses to read or save through a symlinked front folder', function () {
    $outside = dirname($this->front, 2).'/outside-front';
    rename($this->front, $outside);
    symlink($outside, $this->front);
    $before = file_get_contents($outside.'/pages/services.en.md');

    expect(app(FrontKit::class)->read('demo', 'pages/services.en.md'))->toBeNull()
        ->and(fn () => $this->editor->savePage('services', 'en', ['title' => 'A', 'description' => 'B', 'body' => 'C'], $this->actor))
        ->toThrow(AgentRefusedException::class)
        ->and(fn () => $this->editor->saveFacts(['phone' => '+1 239 555 0100'], $this->actor))
        ->toThrow(AgentRefusedException::class);

    expect(file_get_contents($outside.'/pages/services.en.md'))->toBe($before);
});

it('writes theme tokens and facts, keeping the facts it does not know', function () {
    $token = array_key_first($this->editor->themeTokens());

    $this->editor->saveTheme([$token => '#123456'], $this->actor);
    $this->editor->saveFacts(['phone' => '+1 239 555 0100'], $this->actor);

    expect($this->editor->themeTokens()[$token])->toBe('#123456')
        ->and($this->editor->facts()['phone'])->toBe('+1 239 555 0100')
        ->and($this->editor->facts()['name'])->not->toBeEmpty();
});
