<?php

use App\Models\Customer;
use App\Models\User;

it('keeps a portal client out of the staff screens', function () {
    $mine = Customer::factory()->create();
    $other = Customer::factory()->create(['name' => 'Owen Stranger']);
    $client = User::factory()->client()->create();
    $client->customers()->attach($mine);

    $this->actingAs($client);

    foreach ([
        route('customers.index'),
        route('customers.create'),
        route('customers.edit', $other),
        route('client-requests.index'),
        route('users.index'),
    ] as $url) {
        $this->get($url)->assertForbidden();
    }

    $this->post(route('customers.store'), ['name' => 'Made by a client'])->assertForbidden();
    $this->put(route('customers.update', $other), ['name' => 'Renamed by a client'])->assertForbidden();

    expect(Customer::query()->where('name', 'Made by a client')->exists())->toBeFalse()
        ->and($other->fresh()->name)->toBe('Owen Stranger');
});

it('keeps a portal client out of non-public plugin screens', function () {
    $this->artisan('plugin:sync');

    $this->actingAs(User::factory()->client()->create())
        ->get(route('customer-notes.index'))
        ->assertForbidden();
});

it('still sends a portal client from the dashboard to the portal', function () {
    $this->actingAs(User::factory()->client()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('portal.home'));
});

it('still lets staff open the staff screens', function () {
    $this->actingAs(User::factory()->user()->create())
        ->get(route('customers.index'))
        ->assertOk();
});
