<?php

use App\Core\Agents\Actor;
use App\Core\Agents\AgentRefusedException;
use App\Core\Front\FrontEditor;
use App\Core\Front\FrontKit;
use App\Models\FrontRevision;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

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
        ->and(fn () => $this->editor->saveFacts(['phone' => '+1 555 555 0100'], $this->actor))
        ->toThrow(AgentRefusedException::class);

    expect(file_get_contents($outside.'/pages/services.en.md'))->toBe($before);
});

it('writes theme tokens and facts, keeping the facts it does not know', function () {
    $token = array_key_first($this->editor->themeTokens());

    $this->editor->saveTheme([$token => '#123456'], $this->actor);
    $this->editor->saveFacts(['phone' => '+1 555 555 0100'], $this->actor);

    expect($this->editor->themeTokens()[$token])->toBe('#123456')
        ->and($this->editor->facts()['phone'])->toBe('+1 555 555 0100')
        ->and($this->editor->facts()['name'])->not->toBeEmpty();
});

it('refuses to save a page with a never-write phrase and leaves the live file as it was', function () {
    $this->editor->saveFacts(['never_write' => ['best in town']], $this->actor);
    $original = file_get_contents($this->front.'/pages/services.en.md');
    $revisions = FrontRevision::query()->count();

    expect(fn () => $this->editor->savePage('services', 'en', ['title' => 'Services', 'description' => 'What we do.', 'body' => 'We are the Best in town.'], $this->actor))
        ->toThrow(AgentRefusedException::class, 'Never-write phrase "best in town"');

    expect(file_get_contents($this->front.'/pages/services.en.md'))->toBe($original)
        ->and(FrontRevision::query()->count())->toBe($revisions);
});

it('commits every save in the content folder git history', function () {
    $this->editor->savePage('services', 'en', ['title' => 'Our services', 'description' => 'What we do.', 'body' => 'New body.'], $this->actor);

    $log = Process::path(dirname($this->front))->run(['git', 'log', '--format=%an|%s'])->output();

    expect($log)->toContain($this->actor->label().'|Edited pages/services.en.md');
});

it('copies the starter site into an empty content folder once and never over existing content', function () {
    $root = dirname($this->front, 2);
    config(['agents.paths.fronts' => 'content', 'agents.paths.starter' => 'fronts']);

    $this->artisan('front:install')->assertSuccessful();

    expect(file_get_contents($root.'/content/demo/pages/home.en.md'))->toBe(file_get_contents($this->front.'/pages/home.en.md'))
        ->and(Process::path($root.'/content')->run(['git', 'log', '--format=%s'])->output())->toContain('Starter content from demo');

    file_put_contents($root.'/content/demo/pages/home.en.md', 'client edit');
    $this->artisan('front:install')->assertSuccessful();

    expect(file_get_contents($root.'/content/demo/pages/home.en.md'))->toBe('client edit');
});

it('refuses a never-write phrase in the frontmatter on create and on restore', function () {
    $this->editor->savePage('services', 'en', ['title' => 'Rock bottom services', 'description' => 'What we do.', 'body' => 'Body.'], $this->actor);
    $banned = FrontRevision::query()->where('file', 'pages/services.en.md')->latest('id')->first();
    $this->editor->savePage('services', 'en', ['title' => 'Services', 'description' => 'What we do.', 'body' => 'Body.'], $this->actor);
    $this->editor->saveFacts(['never_write' => ['rock bottom']], $this->actor);

    $refusal = function (Closure $save): ?string {
        try {
            $save();
        } catch (AgentRefusedException $exception) {
            return $exception->reason;
        }

        return null;
    };

    expect($refusal(fn () => $this->editor->createPage('about', 'en', ['title' => 'Rock bottom prices', 'description' => 'About us.'], $this->actor)))->toBe('invalid_params')
        ->and($refusal(fn () => $this->editor->restore('pages/services.en.md', $banned->id, $this->actor)))->toBe('invalid_params')
        ->and(is_file($this->front.'/pages/about.en.md'))->toBeFalse();
});

it('keeps the saved file and its revision when the git commit fails', function () {
    Process::fake(['*' => Process::result('', 'fatal: Unable to create index.lock', 128)]);

    $this->editor->savePage('services', 'en', ['title' => 'Our services', 'description' => 'What we do.', 'body' => 'New body.'], $this->actor);

    expect(file_get_contents($this->front.'/pages/services.en.md'))->toContain('New body.')
        ->and(FrontRevision::query()->where('file', 'pages/services.en.md')->latest('id')->value('content'))->toContain('New body.');
});

it('takes a full snapshot of content that is already in place', function () {
    $root = dirname($this->front, 2);
    config(['agents.paths.fronts' => 'content', 'agents.paths.starter' => 'fronts']);
    File::copyDirectory($this->front, $root.'/content/demo');

    $this->artisan('front:install')->assertSuccessful();

    $tracked = Process::path($root.'/content')->run(['git', 'ls-files'])->output();

    expect($tracked)->toContain('demo/facts.yaml')
        ->and($tracked)->toContain('demo/lang/')
        ->and($tracked)->toContain('demo/skills/');
});

it('refuses an SVG asset with active content and keeps a plain one', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 8 8"><circle cx="4" cy="4" r="4"/><text x="0" y="8">Turn on = off</text></svg>';
    $reason = function (string $content): ?string {
        try {
            $this->editor->putAsset('icons/x.svg', $content, $this->actor);
        } catch (AgentRefusedException $exception) {
            return $exception->getMessage();
        }

        return null;
    };

    expect($reason('<svg xmlns="http://www.w3.org/2000/svg"><SCRIPT>alert(1)</SCRIPT></svg>'))->toContain('a <script> element')
        ->and($reason('<svg xmlns="http://www.w3.org/2000/svg"><image href="x.png" onerror="alert(1)"/></svg>'))->toContain('an on…= event attribute')
        ->and($reason('<svg xmlns="http://www.w3.org/2000/svg"><a xlink:href=" JavaScript:alert(1)"><rect/></a></svg>'))->toContain('a javascript: URL')
        ->and($reason('<svg xmlns="http://www.w3.org/2000/svg"><foreignobject/></svg>'))->toContain('a <foreignObject> element')
        ->and(is_file($this->front.'/assets/icons/x.svg'))->toBeFalse()
        ->and($reason($svg))->toBeNull()
        ->and(file_get_contents($this->front.'/assets/icons/x.svg'))->toBe($svg);
});
