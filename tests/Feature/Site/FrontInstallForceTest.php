<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

require_once __DIR__.'/SiteHelpers.php';

beforeEach(function () {
    $this->front = siteSandbox();
    $this->root = dirname($this->front, 2);
    config(['agents.paths.fronts' => 'content', 'agents.paths.starter' => 'fronts']);
});

it('replaces existing content with the shipped front after committing a snapshot of it', function () {
    $this->artisan('front:install')->assertSuccessful();
    file_put_contents($this->root.'/content/demo/pages/home.en.md', 'client edit');
    file_put_contents($this->root.'/content/demo/pages/extra.en.md', 'stale page');

    $this->artisan('front:install', ['--force' => true])->assertSuccessful();

    $git = fn (array $arguments): string => Process::path($this->root.'/content')->run(['git', ...$arguments])->output();

    expect(file_get_contents($this->root.'/content/demo/pages/home.en.md'))->toBe(file_get_contents($this->front.'/pages/home.en.md'))
        ->and(file_exists($this->root.'/content/demo/pages/extra.en.md'))->toBeFalse()
        ->and(file_exists($this->root.'/content/demo/assets/hero-art.svg'))->toBeTrue()
        ->and($git(['log', '--format=%s']))->toContain("Replaced with the shipped front from demo\nSnapshot before front:install --force\nStarter content from demo")
        ->and($git(['show', 'HEAD~1:demo/pages/home.en.md']))->toBe('client edit');
});

it('leaves existing content alone when the company has no shipped front of its own', function () {
    config(['agents.company' => 'other']);
    File::ensureDirectoryExists($this->root.'/content/other/pages');
    file_put_contents($this->root.'/content/other/pages/home.en.md', 'live site');

    $this->artisan('front:install', ['--force' => true])->assertFailed();

    expect(file_get_contents($this->root.'/content/other/pages/home.en.md'))->toBe('live site');
});
