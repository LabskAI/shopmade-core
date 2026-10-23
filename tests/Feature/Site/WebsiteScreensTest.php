<?php

use App\Models\FrontRevision;
use App\Models\User;

require_once __DIR__.'/SiteHelpers.php';

beforeEach(function () {
    $this->front = siteSandbox();
});

it('lets admins and super admins open every website screen', function (string $role, string $route, array $parameters) {
    $this->actingAs(User::factory()->{$role}()->create())
        ->get(route($route, $parameters))
        ->assertOk();
})->with(['admin', 'superAdmin'])->with([
    'pages' => ['site.pages.index', []],
    'new page' => ['site.pages.create', []],
    'edit page' => ['site.pages.edit', ['lang' => 'en', 'slug' => 'home']],
    'history' => ['site.pages.history', ['lang' => 'en', 'slug' => 'home']],
    'facts' => ['site.facts.edit', []],
    'theme' => ['site.theme.edit', []],
    'forms' => ['site.forms.show', []],
    'discovery' => ['site.discovery.show', []],
    'connectors' => ['admin.connectors.index', []],
]);

it('keeps crew and portal clients out of the website editor', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create())
        ->get(route('site.pages.index'))
        ->assertForbidden();
})->with(['user', 'client']);

it('saves a page live from the form and restores the previous version from its history', function () {
    $admin = User::factory()->admin()->create();
    $original = file_get_contents($this->front.'/pages/services.en.md');

    $this->actingAs($admin)
        ->put(route('site.pages.update', ['lang' => 'en', 'slug' => 'services']), [
            'title' => 'Services we offer',
            'description' => 'Everything we do.',
            'body' => 'Fresh copy.',
        ])
        ->assertRedirect(route('site.pages.edit', ['lang' => 'en', 'slug' => 'services']))
        ->assertSessionHas('status', fn (string $status): bool => str_starts_with($status, 'Saved'));

    expect(file_get_contents($this->front.'/pages/services.en.md'))->toContain('Fresh copy.');

    $this->actingAs($admin)
        ->get(route('site.pages.history', ['lang' => 'en', 'slug' => 'services']))
        ->assertOk()
        ->assertSee('Before the first edit in Shopmade')
        ->assertSee($admin->name);

    $first = FrontRevision::query()->oldest('id')->first();

    $this->actingAs($admin)
        ->post(route('site.pages.restore', ['lang' => 'en', 'slug' => 'services']), ['revision' => $first->id])
        ->assertRedirect(route('site.pages.edit', ['lang' => 'en', 'slug' => 'services']));

    expect(file_get_contents($this->front.'/pages/services.en.md'))->toBe($original);
});

it('does not offer delete on the home page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('site.pages.edit', ['lang' => 'en', 'slug' => 'home']))
        ->assertOk()
        ->assertDontSee('Delete page');
});
