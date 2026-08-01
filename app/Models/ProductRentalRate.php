<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductRentalRate extends Model
{
    protected $fillable = [
        'product_id', 'label', 'period_unit', 'period_count', 'price',
        'deposit_amount', 'is_default', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (ProductRentalRate $rate): void {
            if ($rate->price !== null || ! $rate->product_id) {
                return;
            }

            $rate->price = Product::query()->whereKey($rate->product_id)->value('rental_price') ?? 0;
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
