<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceFeePolicy extends Model
{
    protected $fillable = ['code', 'name', 'transaction_type', 'buyer_fee_rate', 'buyer_fixed_fee', 'seller_fee_rate', 'seller_fixed_fee', 'tax_rate', 'priority', 'is_active', 'effective_from', 'effective_to', 'conditions', 'created_by', 'updated_by'];

    protected $casts = ['buyer_fee_rate' => 'decimal:4', 'buyer_fixed_fee' => 'decimal:2', 'seller_fee_rate' => 'decimal:4', 'seller_fixed_fee' => 'decimal:2', 'tax_rate' => 'decimal:4', 'priority' => 'integer', 'is_active' => 'boolean', 'effective_from' => 'datetime', 'effective_to' => 'datetime', 'conditions' => 'array'];
}
