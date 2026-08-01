<?php

namespace App\Models;

use App\Models\Concerns\TracksAuditHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionPayment extends Model
{
    use TracksAuditHistory;

    protected $fillable = ['code', 'transaction_id', 'customer_id', 'payment_type', 'component_type', 'installment_number', 'cycle_number', 'amount', 'refundable', 'payment_method', 'status', 'settlement_status', 'reference', 'period_start', 'period_end', 'due_date', 'paid_at', 'confirmed_at', 'settled_at', 'released_at', 'confirmed_by', 'wallet_transaction_id', 'note'];

    protected $casts = ['amount' => 'decimal:2', 'refundable' => 'boolean', 'period_start' => 'date', 'period_end' => 'date', 'due_date' => 'date', 'paid_at' => 'datetime', 'confirmed_at' => 'datetime', 'settled_at' => 'datetime', 'released_at' => 'datetime'];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
