<?php

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('../plugins');

/**
 * A portal client with access to the given customer.
 */
function portalClient(Customer $customer): User
{
    $client = User::factory()->client()->create();
    $client->customers()->attach($customer);

    return $client;
}
