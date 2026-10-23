<?php

use App\Enums\PluginStatus;
use App\Models\Plugin;

it('upserts disk plugins and preserves status', function () {
    $this->artisan('plugin:sync')->assertSuccessful();

    $plugin = Plugin::query()->where('slug', 'customer-notes')->first();

    expect($plugin)->not->toBeNull()
        ->and($plugin->status)->toBe(PluginStatus::Active)
        ->and($plugin->tables)->toBe(['p_customer_notes_notes']);

    $plugin->update(['status' => PluginStatus::Disabled]);

    $this->artisan('plugin:sync')->assertSuccessful();

    expect($plugin->fresh()->status)->toBe(PluginStatus::Disabled)
        ->and($plugin->fresh()->name)->toBe('Customer Notes');
});

it('marks vanished plugin folders as removed', function () {
    Plugin::factory()->create([
        'slug' => 'gone',
        'status' => PluginStatus::Active,
    ]);

    $this->artisan('plugin:sync')->assertSuccessful();

    expect(Plugin::query()->where('slug', 'gone')->first()?->status)->toBe(PluginStatus::Removed);
});

it('reactivates a removed plugin whose folder is back on disk', function () {
    Plugin::factory()->create([
        'slug' => 'customer-notes',
        'status' => PluginStatus::Removed,
    ]);

    $this->artisan('plugin:sync')->assertSuccessful();

    expect(Plugin::query()->where('slug', 'customer-notes')->first()?->status)->toBe(PluginStatus::Active);
});
