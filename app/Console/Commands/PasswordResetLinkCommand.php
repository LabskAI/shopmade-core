<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;

#[Signature('user:reset-link {email}')]
#[Description('Print a password reset link for a user (for installs without working mail).')]
class PasswordResetLinkCommand extends Command
{
    public function handle(): int
    {
        $email = strtolower((string) $this->argument('email'));
        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();

        if ($user === null) {
            $this->error("No user found for {$email}.");

            return self::FAILURE;
        }

        $token = Password::broker()->createToken($user);

        $this->line(route('password.reset', ['token' => $token, 'email' => $user->email]));

        return self::SUCCESS;
    }
}
