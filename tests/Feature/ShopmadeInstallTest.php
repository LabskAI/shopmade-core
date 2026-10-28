<?php

use Illuminate\Support\Composer;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->composerJson = File::get(base_path('composer.json'));
});

afterEach(function () {
    File::put(base_path('composer.json'), $this->composerJson);
});

it('requires the packages named in --packages from their GitHub repos', function () {
    $this->mock(Composer::class)
        ->shouldReceive('requirePackages')
        ->once()
        ->withArgs(fn (array $packages): bool => $packages === [
            'labskai/shopmade-jobs:^0.1 || dev-main',
            'labskai/shopmade-invoicing:^0.1 || dev-main',
        ])
        ->andReturn(true);

    $this->artisan('shopmade:install', ['--packages' => 'jobs, Invoicing,jobs', '--no-interaction' => true])
        ->assertSuccessful();

    $repositories = json_decode(File::get(base_path('composer.json')), true)['repositories'];

    expect($repositories)->toMatchArray([
        'labskai-shopmade-jobs' => ['type' => 'vcs', 'url' => 'https://github.com/LabskAI/shopmade-jobs', 'no-api' => true],
        'labskai-shopmade-invoicing' => ['type' => 'vcs', 'url' => 'https://github.com/LabskAI/shopmade-invoicing', 'no-api' => true],
    ]);
});

it('leaves the dev dependencies out with --no-dev', function () {
    $this->mock(Composer::class)
        ->shouldReceive('requirePackages')
        ->once()
        ->withArgs(fn (array $packages): bool => end($packages) === '--update-no-dev')
        ->andReturn(true);

    $this->artisan('shopmade:install', ['--packages' => 'crm', '--no-dev' => true, '--no-interaction' => true])
        ->assertSuccessful();
});

it('switches a production .env to local with --local and leaves any other .env alone', function () {
    $directory = storage_path('framework/testing/install-env');
    File::ensureDirectoryExists($directory);
    app()->useEnvironmentPath($directory);
    $this->mock(Composer::class)->shouldNotReceive('requirePackages');

    File::put($directory.'/.env', "APP_ENV=production\nAPP_DEBUG=false\nAPP_KEY=keep\n");
    $this->artisan('shopmade:install', ['--none' => true, '--local' => true])->assertSuccessful();
    expect(File::get($directory.'/.env'))->toBe("APP_ENV=local\nAPP_DEBUG=true\nAPP_KEY=keep\n");

    File::put($directory.'/.env', "APP_ENV=staging\nAPP_DEBUG=false\n");
    $this->artisan('shopmade:install', ['--none' => true, '--local' => true])->assertSuccessful();
    expect(File::get($directory.'/.env'))->toBe("APP_ENV=staging\nAPP_DEBUG=false\n");

    File::deleteDirectory($directory);
});

it('refuses a package it does not offer and installs nothing', function () {
    $this->mock(Composer::class)->shouldNotReceive('requirePackages');

    $this->artisan('shopmade:install', ['--packages' => 'jobs,outreach', '--no-interaction' => true])
        ->expectsOutputToContain('Unknown package: outreach')
        ->assertFailed();
});

it('installs nothing without a choice when not interactive', function () {
    $this->mock(Composer::class)->shouldNotReceive('requirePackages');

    $this->artisan('shopmade:install', ['--no-interaction' => true])
        ->expectsOutputToContain('docker compose up -d --build')
        ->assertSuccessful();
});

it('fails when composer cannot install the packages', function () {
    $this->mock(Composer::class)->shouldReceive('requirePackages')->once()->andReturn(false);

    $this->artisan('shopmade:install', ['--packages' => 'crm', '--no-interaction' => true])
        ->assertFailed();
});

it('never offers a private package', function () {
    expect(collect(config('shopmade.packages'))->pluck('package'))
        ->not->toContain('labskai/shopmade-outreach');
});
