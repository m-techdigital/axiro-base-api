<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProductAvailabilityHistory extends Model
{
    protected $fillable = ['product_id', 'from_status', 'to_status', 'customer_id', 'hold_until', 'source_type', 'source_id', 'note', 'changed_by'];

    protected $casts = ['hold_until' => 'datetime'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
