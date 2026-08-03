<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceNotification extends Model
{
    protected $fillable = ['customer_id', 'transaction_id', 'transaction_code', 'type', 'title', 'message', 'action_url', 'data', 'read_at', 'handled_at', 'handled_by', 'handling_note'];

    protected $casts = ['data' => 'array', 'read_at' => 'datetime', 'handled_at' => 'datetime'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
