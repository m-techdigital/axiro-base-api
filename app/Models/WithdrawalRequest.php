<?php

namespace App\Models;

use App\Models\Concerns\TracksAuditHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithdrawalRequest extends Model
{
    use TracksAuditHistory;

    protected $fillable = ['code', 'idempotency_key', 'customer_id', 'payout_account_id', 'amount', 'fee_amount', 'net_amount', 'status', 'reserved_wallet_transaction_id', 'payment_reference', 'proof_url', 'customer_note', 'review_note', 'submitted_at', 'approved_at', 'paid_at', 'reviewed_by'];

    protected $casts = ['amount' => 'decimal:2', 'fee_amount' => 'decimal:2', 'net_amount' => 'decimal:2', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'paid_at' => 'datetime'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function payoutAccount(): BelongsTo
    {
        return $this->belongsTo(CustomerPayoutAccount::class, 'payout_account_id');
    }
}
