<?php
namespace App\Models;
use App\Models\Concerns\TracksAuditHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class WalletTransaction extends Model {
    use TracksAuditHistory;
    protected $fillable = ['code','idempotency_key','customer_id','transaction_id','transaction_payment_id','type','direction','balance_bucket','amount','available_before','available_after','held_before','held_after','balance_after','status','reference_type','reference_id','payment_method','external_reference','proof_image_url','submitted_at','review_note','metadata','note','occurred_at','confirmed_at','confirmed_by'];
    protected $casts = ['amount'=>'decimal:2','available_before'=>'decimal:2','available_after'=>'decimal:2','held_before'=>'decimal:2','held_after'=>'decimal:2','balance_after'=>'decimal:2','metadata'=>'array','occurred_at'=>'datetime','submitted_at'=>'datetime','confirmed_at'=>'datetime'];
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
}
