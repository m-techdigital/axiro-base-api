<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerWallet extends Model
{
    protected $attributes = [
        'available_balance' => '0.00',
        'held_balance' => '0.00',
        'lifetime_credit' => '0.00',
        'lifetime_debit' => '0.00',
        'version' => 0,
    ];

    protected $fillable = ['customer_id', 'available_balance', 'held_balance', 'lifetime_credit', 'lifetime_debit', 'version'];

    protected $casts = ['available_balance' => 'decimal:2', 'held_balance' => 'decimal:2', 'lifetime_credit' => 'decimal:2', 'lifetime_debit' => 'decimal:2'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
