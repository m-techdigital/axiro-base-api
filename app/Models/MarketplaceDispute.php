<?php

namespace App\Models;

use App\Models\Concerns\TracksAuditHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceDispute extends Model
{
    use TracksAuditHistory;

    protected $attributes = ['status' => 'open', 'case_type' => 'dispute', 'priority' => 'normal'];

    protected $fillable = ['code', 'transaction_id', 'opened_by_customer_id', 'case_type', 'reason', 'status', 'priority', 'description', 'evidence', 'resolution', 'resolved_at', 'resolved_by', 'assigned_to', 'due_at', 'last_message_at'];

    protected $casts = ['evidence' => 'array', 'resolved_at' => 'datetime', 'due_at' => 'datetime', 'last_message_at' => 'datetime'];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'opened_by_customer_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MarketplaceCaseMessage::class, 'case_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
