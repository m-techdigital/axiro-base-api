<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MarketplaceCaseMessage extends Model {
 protected $fillable=['case_id','actor_type','actor_id','message','attachments','is_internal'];
 protected $casts=['attachments'=>'array','is_internal'=>'boolean'];
 public function case(){return $this->belongsTo(MarketplaceDispute::class,'case_id');}
}
