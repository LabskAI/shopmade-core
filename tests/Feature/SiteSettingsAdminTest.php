<?php

use App\Models\Setting;
use App\Models\User;

it('shows the current organization settings', function () {
    Setting::put('org.name', 'Demo Home Services');

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.settings.edit'))
        ->assertOk()
        ->assertSee('Demo Home Services');
});

it('saves organization settings', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.settings.update'), [
            'org_name' => 'Demo Home Services',
            'org_logo' => '/images/demo-logo.svg',
        ])
        ->assertRedirect(route('admin.settings.edit'))
        ->assertSessionHasNoErrors();

    expect(Setting::get('org.name'))->toBe('Demo Home Services')
        ->and(Setting::get('org.logo'))->toBe('/images/demo-logo.svg');
});

it('forbids crew from settings', function (string $role) {
    Setting::put('org.name', 'Shopmade');
    $member = User::factory()->{$role}()->create();

    $this->actingAs($member)->get(route('admin.settings.edit'))->assertForbidden();

    $this->actingAs($member)
        ->put(route('admin.settings.update'), ['org_name' => 'Hijacked'])
        ->assertForbidden();

    expect(Setting::get('org.name'))->toBe('Shopmade');
})->with(['user']);
