<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EscrowFeeRule extends Model
{
    protected $fillable = ['code', 'name', 'minimum_money_amount', 'maximum_money_amount', 'base_fee', 'percentage_rate', 'minimum_fee', 'maximum_fee', 'priority', 'version', 'is_active', 'effective_from', 'effective_to', 'conditions', 'created_by', 'updated_by'];
    protected $casts = ['minimum_money_amount' => 'decimal:2', 'maximum_money_amount' => 'decimal:2', 'base_fee' => 'decimal:2', 'percentage_rate' => 'decimal:4', 'minimum_fee' => 'decimal:2', 'maximum_fee' => 'decimal:2', 'is_active' => 'boolean', 'effective_from' => 'datetime', 'effective_to' => 'datetime', 'conditions' => 'array'];
}
