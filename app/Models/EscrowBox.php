<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EscrowBox extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'invite_token_hash', 'invite_expires_at', 'invite_claimed_at', 'invite_generation',
        'created_by_customer_id', 'created_by_user_id', 'initiation_source', 'party_a_customer_id', 'party_b_customer_id',
        'party_a_invite_token_hash', 'party_b_invite_token_hash', 'party_a_invite_expires_at', 'party_b_invite_expires_at',
        'party_a_invite_accepted_at', 'party_b_invite_accepted_at', 'status', 'deal_type',
        'agreement_version', 'agreement_terms', 'party_a_confirmed_at', 'party_b_confirmed_at',
        'party_a_confirmed_version', 'party_b_confirmed_version', 'topup_payer_side', 'topup_amount',
        'fee_payer_mode', 'party_a_fee_amount', 'party_b_fee_amount', 'calculated_fee', 'final_fee',
        'fee_rule_id', 'fee_rule_version', 'fee_snapshot', 'fee_override_reason', 'fee_overridden_by',
        'fee_overridden_at', 'risk_level', 'admin_review_note', 'reviewed_by', 'reviewed_at',
        'handover_sequence', 'inspection_period_minutes', 'inspection_started_at', 'inspection_deadline_at',
        'party_a_received_at', 'party_b_received_at', 'transaction_id', 'expected_version', 'expires_at',
    ];

    protected $hidden = [
        'invite_token_hash',
        'party_a_invite_token_hash',
        'party_b_invite_token_hash',
    ];

    protected $casts = [
        'invite_expires_at' => 'datetime', 'invite_claimed_at' => 'datetime',
        'party_a_invite_expires_at' => 'datetime', 'party_b_invite_expires_at' => 'datetime',
        'party_a_invite_accepted_at' => 'datetime', 'party_b_invite_accepted_at' => 'datetime', 'agreement_terms' => 'array',
        'party_a_confirmed_at' => 'datetime', 'party_b_confirmed_at' => 'datetime', 'topup_amount' => 'decimal:2',
        'party_a_fee_amount' => 'decimal:2', 'party_b_fee_amount' => 'decimal:2', 'calculated_fee' => 'decimal:2',
        'final_fee' => 'decimal:2', 'fee_snapshot' => 'array', 'fee_overridden_at' => 'datetime',
        'reviewed_at' => 'datetime', 'inspection_started_at' => 'datetime', 'inspection_deadline_at' => 'datetime',
        'party_a_received_at' => 'datetime', 'party_b_received_at' => 'datetime', 'expires_at' => 'datetime', 'agreement_version' => 'integer', 'expected_version' => 'integer',
    ];

    public function partyA(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'party_a_customer_id');
    }

    public function partyB(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'party_b_customer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'created_by_customer_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function feeRule(): BelongsTo
    {
        return $this->belongsTo(EscrowFeeRule::class, 'fee_rule_id');
    }

    public function agreementVersions(): HasMany
    {
        return $this->hasMany(EscrowBoxAgreementVersion::class);
    }

    public function obligations(): HasMany
    {
        return $this->hasMany(EscrowBoxPaymentObligation::class);
    }

    public function handoverSteps(): HasMany
    {
        return $this->hasMany(EscrowBoxHandoverStep::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(EscrowBoxMedia::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(EscrowBoxEvent::class);
    }
}
