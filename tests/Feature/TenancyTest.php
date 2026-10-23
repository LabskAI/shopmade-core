<?php

use App\Models\Customer;
use App\Models\User;

it('shows every customer to every role', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->user()->create();

    Customer::factory()->create(['name' => 'Helen Park']);
    Customer::factory()->create(['name' => 'Owen Client']);

    $this->actingAs($user)
        ->get(route('customers.index'))
        ->assertOk()
        ->assertSee('Helen Park')
        ->assertSee('Owen Client');

    $this->actingAs($admin)
        ->get(route('customers.index'))
        ->assertOk()
        ->assertSee('Helen Park')
        ->assertSee('Owen Client');
});
