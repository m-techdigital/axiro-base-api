<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceNotification extends Model
{
    protected $fillable = ['customer_id', 'transaction_id', 'transaction_code', 'type', 'title', 'message', 'action_url', 'data', 'read_at'];

    protected $casts = ['data' => 'array', 'read_at' => 'datetime'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
