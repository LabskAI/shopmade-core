<?php

use App\Core\Plugins\Registry;
use App\Enums\PluginStatus;
use App\Models\Customer;
use App\Models\Plugin;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Plugins\CustomerNotes\Models\CustomerNote;

it('shows an active plugin in nav and serves its screens', function () {
    $this->artisan('plugin:sync');

    $user = User::factory()->user()->create();
    $customer = Customer::factory()->create();

    CustomerNote::query()->create([
        'customer_id' => $customer->id,
        'body' => 'Wipe the baseboards.',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Customer notes');

    $this->actingAs($user)
        ->get(route('customer-notes.index'))
        ->assertOk()
        ->assertSee('Wipe the baseboards.');

    $this->actingAs($user)
        ->get(route('customers.show', $customer))
        ->assertOk()
        ->assertSee(route('customer-notes.customer', $customer), false);
});

it('hides a disabled plugin from nav and returns 404', function () {
    $this->artisan('plugin:sync');

    Plugin::query()->where('slug', 'customer-notes')->first()?->update([
        'status' => PluginStatus::Disabled,
    ]);

    $user = User::factory()->user()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('>Customer notes</', false);

    $this->actingAs($user)
        ->get(route('customer-notes.index'))
        ->assertNotFound();
});

it('still boots when a plugin.json is invalid', function () {
    $dir = base_path('plugins/zz-broken');
    File::ensureDirectoryExists($dir);
    File::put($dir.'/plugin.json', '{not-json');

    try {
        $slugs = collect(app(Registry::class)->discover())->pluck('slug');

        expect($slugs)->not->toContain('zz-broken');

        $this->get(route('login'))->assertOk();
    } finally {
        File::deleteDirectory($dir);
    }
});
