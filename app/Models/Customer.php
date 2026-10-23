<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'phone',
    'email',
    'address',
    'notes',
    'billing_name',
    'primary_contact_name',
    'primary_contact_email',
    'visit_rate',
    'payment_terms',
])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'visit_rate' => 'decimal:2',
        ];
    }

    public function portalUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(CustomerChangeRequest::class);
    }

    public function visitRateLabel(): ?string
    {
        if ($this->visit_rate === null) {
            return null;
        }

        $amount = (float) $this->visit_rate;
        $formatted = floor($amount) == $amount
            ? number_format($amount, 0)
            : number_format($amount, 2);

        return '$'.$formatted.'/visit';
    }
}
