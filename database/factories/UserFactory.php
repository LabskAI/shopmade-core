<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'must_set_password' => false,
            'password_invite_version' => 0,
            'role' => UserRole::User,
            'remember_token' => Str::random(10),
        ];
    }

    public function client(): static
    {
        return $this->state(fn () => ['role' => UserRole::Client]);
    }

    public function pendingPassword(): static
    {
        return $this->state(fn () => [
            'must_set_password' => true,
            'password_invite_version' => 1,
        ]);
    }

    public function deactivated(): static
    {
        return $this->state(fn (): array => ['deactivated_at' => now()]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => ['role' => UserRole::SuperAdmin]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => UserRole::Admin]);
    }

    public function user(): static
    {
        return $this->state(fn () => ['role' => UserRole::User]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => [
            'email_verified_at' => null,
        ]);
    }
}
