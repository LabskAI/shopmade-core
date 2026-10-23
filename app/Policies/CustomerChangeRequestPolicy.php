<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\CustomerChangeRequest;
use App\Models\User;

class CustomerChangeRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isClient();
    }

    public function view(User $user, CustomerChangeRequest $customerChangeRequest): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user, Customer $customer): bool
    {
        return $user->isClient() && $this->isMember($user, $customer);
    }

    private function isMember(User $user, Customer $customer): bool
    {
        return $user->customers()->where('customers.id', $customer->id)->exists();
    }
}
