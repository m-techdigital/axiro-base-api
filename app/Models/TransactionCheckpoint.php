<?php

namespace App\Models;

use App\Models\Concerns\TracksAuditHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionCheckpoint extends Model
{
    use TracksAuditHistory;

    protected $fillable = ['transaction_id', 'checkpoint', 'customer_id', 'actor_type', 'actor_id', 'note', 'metadata', 'confirmed_at'];

    protected $casts = ['metadata' => 'array', 'confirmed_at' => 'datetime'];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
