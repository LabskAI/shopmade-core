<?php

namespace Database\Factories;

use App\Models\ContactMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactMessage>
 */
class ContactMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company' => 'demo',
            'form' => 'contact',
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => null,
            'message' => fake()->paragraph(),
            'page_url' => null,
            'lang' => 'en',
        ];
    }
}
