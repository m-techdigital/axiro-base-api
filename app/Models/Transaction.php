<?php

namespace App\Models;

use App\Models\Concerns\TracksAuditHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use SoftDeletes;
    use TracksAuditHistory;

    protected $fillable = ['code', 'idempotency_key', 'request_hash', 'transaction_type', 'purchase_mode', 'initiation_source', 'agreement_status', 'initiated_by_customer_id', 'agreement_version', 'agreement_terms', 'buyer_accepted_at', 'seller_accepted_at', 'asset_delivery_method', 'inspection_period_minutes', 'requires_pre_handover_snapshot', 'inspection_deadline_at', 'seller_delivery_note', 'buyer_inspection_note', 'product_id', 'buyer_customer_id', 'seller_customer_id', 'transaction_value', 'service_fee', 'buyer_fee_amount', 'seller_fee_amount', 'tax_amount', 'seller_net_amount', 'fee_policy_version', 'fee_snapshot', 'discount', 'deposit_amount', 'initial_payment_amount', 'installment_count', 'installment_interval_unit', 'installment_interval_count', 'rental_period_unit', 'rental_period_count', 'rental_billing_mode', 'rental_billing_cycle_unit', 'rental_billing_cycle_count', 'total_payable', 'paid_amount', 'refunded_amount', 'rental_deposit_deduction_amount', 'rental_deposit_deduction_note', 'escrow_amount', 'released_amount', 'wallet_paid_amount', 'transaction_date', 'due_date', 'next_payment_due_at', 'rental_start_at', 'rental_end_at', 'handed_over_at', 'returned_at', 'completed_at', 'status', 'payment_method', 'note', 'created_by', 'updated_by'];

    protected $casts = ['agreement_version' => 'integer', 'agreement_terms' => 'array', 'buyer_accepted_at' => 'datetime', 'seller_accepted_at' => 'datetime', 'inspection_period_minutes' => 'integer', 'requires_pre_handover_snapshot' => 'boolean', 'inspection_deadline_at' => 'datetime', 'transaction_value' => 'decimal:2', 'service_fee' => 'decimal:2', 'buyer_fee_amount' => 'decimal:2', 'seller_fee_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'seller_net_amount' => 'decimal:2', 'fee_snapshot' => 'array', 'discount' => 'decimal:2', 'deposit_amount' => 'decimal:2', 'initial_payment_amount' => 'decimal:2', 'total_payable' => 'decimal:2', 'paid_amount' => 'decimal:2', 'refunded_amount' => 'decimal:2', 'rental_deposit_deduction_amount' => 'decimal:2', 'escrow_amount' => 'decimal:2', 'released_amount' => 'decimal:2', 'wallet_paid_amount' => 'decimal:2', 'transaction_date' => 'date', 'due_date' => 'date', 'next_payment_due_at' => 'date', 'rental_start_at' => 'datetime', 'rental_end_at' => 'datetime', 'handed_over_at' => 'datetime', 'returned_at' => 'datetime', 'completed_at' => 'datetime'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'buyer_customer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'seller_customer_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(TransactionPayment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(TransactionEvent::class);
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(MarketplaceDispute::class);
    }

    public function checkpoints(): HasMany
    {
        return $this->hasMany(TransactionCheckpoint::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(GeneratedDocument::class);
    }

    public function assetSnapshots(): HasMany
    {
        return $this->hasMany(TransactionAssetSnapshot::class);
    }
}
