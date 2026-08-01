<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProductHold extends Model
{
    protected $fillable = ['product_id', 'customer_id', 'hold_until', 'source_type', 'source_id', 'status', 'note', 'released_at', 'release_reason'];

    protected $casts = ['hold_until' => 'datetime', 'released_at' => 'datetime'];

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
