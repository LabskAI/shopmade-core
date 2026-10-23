<?php

namespace Plugins\CustomerNotes\Models;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'body'])]
class CustomerNote extends Model
{
    protected $table = 'p_customer_notes_notes';

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
