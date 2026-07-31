<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ListingFavorite extends Model {protected $fillable=['customer_id','listing_id'];public function listing(){return $this->belongsTo(ProductListing::class);}public function customer(){return $this->belongsTo(Customer::class);}}
