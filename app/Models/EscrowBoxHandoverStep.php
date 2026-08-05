<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class EscrowBoxHandoverStep extends Model { protected $fillable = ['escrow_box_id','party_side','step_type','status','sequence_no','customer_note','admin_note','submitted_by_customer_id','submitted_at','verified_by','verified_at','expected_version']; protected $casts=['submitted_at'=>'datetime','verified_at'=>'datetime']; public function media(): HasMany { return $this->hasMany(EscrowBoxMedia::class,'handover_step_id'); } }
