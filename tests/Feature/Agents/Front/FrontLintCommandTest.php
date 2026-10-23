<?php

use Tests\Feature\Agents\Front\FrontFixture;

afterEach(fn () => FrontFixture::cleanup());

it('passes the demo front', function () {
    config(['agents.paths.repo' => base_path()]);

    $this->artisan('front:lint', ['company' => 'demo'])
        ->expectsOutputToContain('front:lint demo: 0 error(s), 0 warning(s).')
        ->assertSuccessful();
});

it('fails quoting the never-write phrase and the sentence it is in', function () {
    FrontFixture::create([
        'pages/home.en.md' => FrontFixture::page(
            ['title' => 'Acme Cleaning in Naples', 'description' => 'House and office cleaning in Naples and Estero.', 'layout' => 'home'],
            "# Clean homes in Naples\n\nWe are OSHA certified.",
        ),
    ]);

    $this->artisan('front:lint', ['company' => 'acme'])
        ->expectsOutputToContain('ERROR [never_write] fronts/acme/pages/home.en.md: Never-write phrase "certified" in body: "We are OSHA certified."')
        ->expectsOutputToContain('ERROR [never_write] fronts/acme/pages/home.en.md: Never-write phrase "OSHA" in body: "We are OSHA certified."')
        ->expectsOutputToContain('front:lint acme: 2 error(s), 0 warning(s).')
        ->assertFailed();
});

it('fails for a company without a front', function () {
    FrontFixture::create();

    $this->artisan('front:lint', ['company' => 'globex'])
        ->expectsOutputToContain('ERROR [front] fronts/globex: The front folder does not exist.')
        ->assertFailed();
});
