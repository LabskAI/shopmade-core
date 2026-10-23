<?php

use App\Models\Customer;
use App\Models\User;
use Plugins\CustomerNotes\Models\CustomerNote;

it('lets staff open the customer notes index', function () {
    $this->artisan('plugin:sync');

    $owner = User::factory()->superAdmin()->create();
    $customer = Customer::factory()->create();

    CustomerNote::query()->create([
        'customer_id' => $customer->id,
        'body' => 'Gate code is 4412.',
    ]);

    $this->actingAs($owner)
        ->get(route('customer-notes.index'))
        ->assertOk()
        ->assertSee('Gate code is 4412.');
});
