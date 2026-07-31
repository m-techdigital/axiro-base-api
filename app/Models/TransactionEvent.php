<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class TransactionEvent extends Model {
    protected $fillable = ['transaction_id','event_type','actor_type','actor_id','title','description','metadata'];
    protected $casts = ['metadata'=>'array'];
    public function transaction(): BelongsTo { return $this->belongsTo(Transaction::class); }
}
