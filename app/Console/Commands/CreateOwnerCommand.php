<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[Signature('user:create-owner {email} {name=Owner}')]
#[Description('Create the first super_admin on a fresh install and print a one-time password.')]
class CreateOwnerCommand extends Command
{
    public function handle(): int
    {
        $email = strtolower((string) $this->argument('email'));

        if (User::query()->whereRaw('lower(email) = ?', [$email])->exists()) {
            $this->error("A user with {$email} already exists. Use user:promote-super-admin instead.");

            return self::FAILURE;
        }

        $password = Str::password(20, symbols: false);

        User::query()->create([
            'name' => (string) $this->argument('name'),
            'email' => $email,
            'password' => Hash::make($password),
            'role' => UserRole::SuperAdmin,
        ]);

        $this->info("Created {$email} as super_admin. Password: {$password}");
        $this->line('Sign in and change it under My account; it is not shown again.');

        return self::SUCCESS;
    }
}
