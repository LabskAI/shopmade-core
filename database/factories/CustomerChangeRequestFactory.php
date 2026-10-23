<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerChangeRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerChangeRequest>
 */
class CustomerChangeRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'user_id' => User::factory()->client(),
            'message' => fake()->sentence(),
        ];
    }
}
