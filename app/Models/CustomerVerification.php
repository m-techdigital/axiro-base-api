<?php
namespace App\Models;
use App\Models\Concerns\TracksAuditHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CustomerVerification extends Model {
 use TracksAuditHistory;
 protected $fillable=['customer_id','status','document_type','document_number','document_front_url','document_back_url','selfie_url','submitted_at','verified_at','verified_by','review_note'];
 protected $casts=['submitted_at'=>'datetime','verified_at'=>'datetime'];
 public function customer(): BelongsTo{return $this->belongsTo(Customer::class);} }
