<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EscrowBoxPaymentObligation extends Model
{
    protected $fillable = ['escrow_box_id', 'party_side', 'type', 'amount', 'status', 'transaction_payment_id', 'paid_at'];

    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'datetime'];
}
