<?php

use App\Models\Customer;
use App\Models\User;

it('shows the client site card on portal home', function () {
    $customer = Customer::factory()->managedSite()->create();
    $client = portalClient($customer);

    $this->actingAs($client)
        ->get(route('portal.home'))
        ->assertOk()
        ->assertSee('Harbor Point Plaza')
        ->assertSee('100 Example Ave, Riverton, ST 00000')
        ->assertSee('Example Property Management, LLC')
        ->assertSee('Dana Example')
        ->assertSee('manager@example.com')
        ->assertSee('$325/visit')
        ->assertSee('Net 15')
        ->assertSee('Request a change');
});

it('does not show another customer account on portal home', function () {
    $mine = Customer::factory()->create(['name' => 'Helen Park']);
    $other = Customer::factory()->managedSite()->create();
    $client = portalClient($mine);

    $this->actingAs($client)
        ->get(route('portal.home'))
        ->assertOk()
        ->assertSee('Helen Park')
        ->assertDontSee('Harbor Point Plaza')
        ->assertDontSee('Example Property Management, LLC')
        ->assertDontSee('Dana Example');
});

it('escapes account fields on the portal home', function () {
    $customer = Customer::factory()->create([
        'name' => 'Harbor <script>alert(1)</script>',
        'billing_name' => 'HOME <b>Estate</b>',
    ]);
    $client = portalClient($customer);

    $this->actingAs($client)
        ->get(route('portal.home'))
        ->assertOk()
        ->assertSee('Harbor <script>alert(1)</script>')
        ->assertSee('HOME <b>Estate</b>')
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('<b>Estate</b>', false);
});

it('lets staff save account fields that the client can see', function () {
    $customer = Customer::factory()->create(['name' => 'Harbor Point Plaza']);
    $client = portalClient($customer);
    $staff = User::factory()->user()->create();

    $this->actingAs($staff)
        ->put(route('customers.update', $customer), [
            'name' => 'Harbor Point Plaza',
            'billing_name' => 'Example Property Management, LLC',
            'primary_contact_name' => 'Dana Example',
            'primary_contact_email' => 'manager@example.com',
            'visit_rate' => '325',
            'payment_terms' => 'Net 15',
        ])
        ->assertRedirect(route('customers.show', $customer));

    $this->assertDatabaseHas('customers', [
        'id' => $customer->id,
        'billing_name' => 'Example Property Management, LLC',
        'primary_contact_name' => 'Dana Example',
        'primary_contact_email' => 'manager@example.com',
        'payment_terms' => 'Net 15',
    ]);

    $this->actingAs($client)
        ->get(route('portal.home'))
        ->assertOk()
        ->assertSee('Example Property Management, LLC')
        ->assertSee('$325/visit')
        ->assertSee('Net 15');
});
