<?php

use Illuminate\Support\Composer;

it('requires the packages named in --packages', function () {
    $this->mock(Composer::class)
        ->shouldReceive('requirePackages')
        ->once()
        ->withArgs(fn (array $packages): bool => $packages === ['labskai/shopmade-jobs', 'labskai/shopmade-invoicing'])
        ->andReturn(true);

    $this->artisan('shopmade:install', ['--packages' => 'jobs, Invoicing,jobs', '--no-interaction' => true])
        ->assertSuccessful();
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
