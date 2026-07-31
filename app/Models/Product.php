<?php
namespace App\Models;
use App\Models\Concerns\TracksAuditHistory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
class Product extends Model {
    use TracksAuditHistory;
    use HasFactory, SoftDeletes;
    protected $fillable = ['code','name','slug','product_type','game_code','server_name','level','status','price','description','image_url','image_urls','attributes','owner_customer_id','created_by','updated_by'];
    protected $casts = ['price'=>'decimal:2','image_urls'=>'array','attributes'=>'array'];
    public function transactions(): HasMany { return $this->hasMany(Transaction::class); }
    public function listings(): HasMany { return $this->hasMany(ProductListing::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class,'created_by'); }
    public function owner(): BelongsTo { return $this->belongsTo(Customer::class,'owner_customer_id'); }
}
