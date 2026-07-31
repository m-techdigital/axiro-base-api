<?php
namespace App\Models;
use App\Models\Concerns\TracksAuditHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ListingRentalRate extends Model {
    use TracksAuditHistory;
    protected $fillable=['product_listing_id','label','period_unit','period_count','price','deposit_amount','is_default','sort_order','is_active'];
    protected $casts=['price'=>'decimal:2','deposit_amount'=>'decimal:2','is_default'=>'boolean','is_active'=>'boolean'];
    public function listing():BelongsTo{return $this->belongsTo(ProductListing::class,'product_listing_id');}
}
