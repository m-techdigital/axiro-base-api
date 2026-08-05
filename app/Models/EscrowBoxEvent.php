<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EscrowBoxEvent extends Model { protected $fillable=['escrow_box_id','event_type','actor_type','actor_id','actor_side','metadata','occurred_at']; protected $casts=['metadata'=>'array','occurred_at'=>'datetime']; }
