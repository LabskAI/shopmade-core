<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('user:promote-super-admin {email}')]
#[Description('Set a user role to super_admin.')]
class PromoteSuperAdminCommand extends Command
{
    public function handle(): int
    {
        $email = strtolower((string) $this->argument('email'));
        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();

        if ($user === null) {
            $this->error("No user found for {$email}.");

            return self::FAILURE;
        }

        $user->forceFill(['role' => UserRole::SuperAdmin])->save();

        $this->info("Promoted {$user->email} to super_admin.");

        return self::SUCCESS;
    }
}
