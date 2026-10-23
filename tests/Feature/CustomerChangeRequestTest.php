<?php

use App\Models\Customer;
use App\Models\CustomerChangeRequest;
use App\Models\User;

it('lets a client submit a change request for a membership customer', function () {
    $customer = Customer::factory()->managedSite()->create();
    $client = portalClient($customer);

    $this->actingAs($client)
        ->post(route('portal.customers.change-requests.store', $customer), [
            'message' => 'Please move next week\'s visit to Thursday morning.',
        ])
        ->assertRedirect(route('portal.home'))
        ->assertSessionHas('status', 'Request sent. We will follow up.');

    $this->assertDatabaseHas('customer_change_requests', [
        'customer_id' => $customer->id,
        'user_id' => $client->id,
        'message' => 'Please move next week\'s visit to Thursday morning.',
    ]);
});

it('forbids a client from requesting a change for another customer', function () {
    $mine = Customer::factory()->create();
    $other = Customer::factory()->create();
    $client = portalClient($mine);

    $this->actingAs($client)
        ->post(route('portal.customers.change-requests.store', $other), [
            'message' => 'Please change the other site.',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('customer_change_requests', [
        'customer_id' => $other->id,
    ]);
});

it('rejects an empty change request message', function () {
    $customer = Customer::factory()->create();
    $client = portalClient($customer);

    $this->actingAs($client)
        ->from(route('portal.home'))
        ->post(route('portal.customers.change-requests.store', $customer), [
            'message' => '',
        ])
        ->assertRedirect(route('portal.home'))
        ->assertSessionHasErrors(['message' => 'The message field is required.']);

    $this->assertDatabaseCount('customer_change_requests', 0);
});

it('keeps staff from submitting a portal change request', function () {
    $customer = Customer::factory()->create();
    $staff = User::factory()->user()->create();

    $this->actingAs($staff)
        ->post(route('portal.customers.change-requests.store', $customer), [
            'message' => 'Staff should use the office tools.',
        ])
        ->assertForbidden();

    $this->assertDatabaseCount('customer_change_requests', 0);
});

it('sends guests to login instead of accepting a change request', function () {
    $customer = Customer::factory()->create();

    $this->post(route('portal.customers.change-requests.store', $customer), [
        'message' => 'Please reschedule.',
    ])->assertRedirect(route('login'));

    $this->assertDatabaseCount('customer_change_requests', 0);
});

it('shows a client request to staff on the customer page and the list', function () {
    $customer = Customer::factory()->managedSite()->create();
    $client = portalClient($customer);
    $staff = User::factory()->user()->create();
    CustomerChangeRequest::factory()->create([
        'customer_id' => $customer->id,
        'user_id' => $client->id,
        'message' => 'Please send the crew after 10am.',
    ]);

    $this->actingAs($staff)
        ->get(route('customers.show', $customer))
        ->assertOk()
        ->assertSee('Client requests')
        ->assertSee('Please send the crew after 10am.')
        ->assertSee($client->name);

    $this->actingAs($staff)
        ->get(route('client-requests.index'))
        ->assertOk()
        ->assertSee('Client requests')
        ->assertSee('Harbor Point Plaza')
        ->assertSee('Please send the crew after 10am.')
        ->assertSee(route('customers.show', $customer), false);
});

it('forbids a client from the staff request list', function () {
    $customer = Customer::factory()->create();
    $client = portalClient($customer);
    CustomerChangeRequest::factory()->create([
        'customer_id' => $customer->id,
        'user_id' => $client->id,
        'message' => 'Secret to this client only via home submit.',
    ]);

    $this->actingAs($client)
        ->get(route('client-requests.index'))
        ->assertForbidden();
});

it('allows a client to create a request only for membership customers', function () {
    $mine = Customer::factory()->create();
    $other = Customer::factory()->create();
    $client = portalClient($mine);
    $staff = User::factory()->admin()->create();

    expect($client->can('create', [CustomerChangeRequest::class, $mine]))->toBeTrue()
        ->and($client->can('create', [CustomerChangeRequest::class, $other]))->toBeFalse()
        ->and($client->can('viewAny', CustomerChangeRequest::class))->toBeFalse()
        ->and($staff->can('viewAny', CustomerChangeRequest::class))->toBeTrue()
        ->and($staff->can('create', [CustomerChangeRequest::class, $mine]))->toBeFalse();
});
