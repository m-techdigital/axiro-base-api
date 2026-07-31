<?php
namespace App\Models;
use App\Models\Concerns\TracksAuditHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
class ProductListing extends Model {
    use TracksAuditHistory;
    use SoftDeletes;
    protected $fillable = ['code','product_id','owner_customer_id','listing_type','status','title','description','sale_price','rental_price','rental_price_unit','minimum_rental_period','rental_period_unit','rental_billing_mode','rental_billing_cycle_unit','rental_billing_cycle_count','deposit_amount','allow_installment','max_installment_count','minimum_initial_payment','installment_interval_unit','installment_interval_count','available_from','available_until','published_at','approved_at','approved_by','rejection_reason'];
    protected $casts = ['sale_price'=>'decimal:2','rental_price'=>'decimal:2','deposit_amount'=>'decimal:2','minimum_initial_payment'=>'decimal:2','allow_installment'=>'boolean','available_from'=>'datetime','available_until'=>'datetime','published_at'=>'datetime','approved_at'=>'datetime'];
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function owner(): BelongsTo { return $this->belongsTo(Customer::class,'owner_customer_id'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class,'approved_by'); }
    public function transactions(): HasMany { return $this->hasMany(Transaction::class,'listing_id'); }
    public function rentalRates(): HasMany { return $this->hasMany(ListingRentalRate::class)->orderBy('sort_order')->orderBy('period_count'); }
}
