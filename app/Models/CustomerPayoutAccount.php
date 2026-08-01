<?php

namespace App\Models;

use App\Models\Concerns\TracksAuditHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPayoutAccount extends Model
{
    use TracksAuditHistory;

    protected $fillable = ['customer_id', 'bank_code', 'bank_name', 'account_name', 'account_number', 'status', 'is_default', 'verified_at', 'verified_by', 'review_note'];

    protected $casts = ['is_default' => 'boolean', 'verified_at' => 'datetime'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
