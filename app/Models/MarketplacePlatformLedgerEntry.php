<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MarketplacePlatformLedgerEntry extends Model {
 protected $fillable=['code','idempotency_key','transaction_id','type','amount','metadata','occurred_at'];
 protected $casts=['amount'=>'decimal:2','metadata'=>'array','occurred_at'=>'datetime'];
}
