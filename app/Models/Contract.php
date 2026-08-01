<?php

namespace App\Models;

use App\Models\Concerns\TracksAuditHistory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use HasFactory,SoftDeletes;
    use TracksAuditHistory;

    protected $fillable = ['code', 'transaction_id', 'contract_type', 'title', 'contract_value', 'deposit_amount', 'signed_at', 'start_date', 'end_date', 'status', 'note', 'created_by', 'updated_by'];

    protected $casts = ['contract_value' => 'decimal:2', 'deposit_amount' => 'decimal:2', 'signed_at' => 'date:Y-m-d', 'start_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d'];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class)->withTrashed();
    }
}
