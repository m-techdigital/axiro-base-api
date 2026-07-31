<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TransactionAssetSnapshot extends Model {
 protected $fillable=['transaction_id','stage','customer_id','actor_type','actor_id','images','attributes','note','captured_at'];
 protected $casts=['images'=>'array','attributes'=>'array','captured_at'=>'datetime'];
 public function transaction(){return $this->belongsTo(Transaction::class);}
 public function customer(){return $this->belongsTo(Customer::class);}
}
