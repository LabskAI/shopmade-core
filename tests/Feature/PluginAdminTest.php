<?php

use App\Enums\PluginStatus;
use App\Models\Plugin;
use App\Models\User;

it('lists synced plugins from the plugins table', function () {
    $this->artisan('plugin:sync')->assertSuccessful();

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.plugins.index'))
        ->assertOk()
        ->assertSee('Customer Notes')
        ->assertSee('customer-notes')
        ->assertSee('1.0.0')
        ->assertSee('local')
        ->assertDontSee('No plugins installed yet');
});

it('redirects the old features admin path to plugins', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/features')
        ->assertRedirect('/admin/plugins');
});

it('disables a plugin so its screens 404 and its nav entry is gone', function () {
    $this->artisan('plugin:sync')->assertSuccessful();

    $admin = User::factory()->admin()->create();
    $plugin = Plugin::query()->where('slug', 'customer-notes')->firstOrFail();

    $this->actingAs($admin)
        ->from(route('admin.plugins.index'))
        ->post(route('admin.plugins.disable', $plugin))
        ->assertRedirect(route('admin.plugins.index'));

    expect($plugin->fresh()->status)->toBe(PluginStatus::Disabled);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('>Customer notes</', false);

    $this->actingAs($admin)
        ->get(route('customer-notes.index'))
        ->assertNotFound();
});

it('enables a disabled plugin so its screens and nav entry come back', function () {
    $this->artisan('plugin:sync')->assertSuccessful();

    $admin = User::factory()->admin()->create();
    $plugin = Plugin::query()->where('slug', 'customer-notes')->firstOrFail();
    $plugin->update(['status' => PluginStatus::Disabled]);

    $this->actingAs($admin)
        ->post(route('admin.plugins.enable', $plugin))
        ->assertRedirect();

    expect($plugin->fresh()->status)->toBe(PluginStatus::Active);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('>Customer notes</', false);

    $this->actingAs($admin)
        ->get(route('customer-notes.index'))
        ->assertOk();
});

it('refuses to enable a removed plugin', function () {
    $admin = User::factory()->admin()->create();
    $plugin = Plugin::factory()->create(['status' => PluginStatus::Removed]);

    $this->actingAs($admin)
        ->post(route('admin.plugins.enable', $plugin))
        ->assertRedirect()
        ->assertSessionHasErrors('plugin');

    expect($plugin->fresh()->status)->toBe(PluginStatus::Removed);
});

it('blocks crew from toggling plugins', function () {
    $builder = User::factory()->user()->create();
    $plugin = Plugin::factory()->create();

    $this->actingAs($builder)
        ->post(route('admin.plugins.disable', $plugin))
        ->assertForbidden();

    expect($plugin->fresh()->status)->toBe(PluginStatus::Active);
});
