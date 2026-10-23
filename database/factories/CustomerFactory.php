<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->numerify('(###) ###-####'),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->streetAddress().', '.fake()->city().', '.fake()->stateAbbr().' '.fake()->postcode(),
            'notes' => fake()->optional(0.4)->sentence(),
            'billing_name' => null,
            'primary_contact_name' => null,
            'primary_contact_email' => null,
            'visit_rate' => null,
            'payment_terms' => null,
        ];
    }

    public function managedSite(): static
    {
        return $this->state(fn () => [
            'name' => 'Harbor Point Plaza',
            'address' => '100 Example Ave, Naples, FL',
            'billing_name' => 'Example Property Management, LLC',
            'primary_contact_name' => 'Dana Example',
            'primary_contact_email' => 'manager@example.com',
            'visit_rate' => '325.00',
            'payment_terms' => 'Net 15',
        ]);
    }
}
