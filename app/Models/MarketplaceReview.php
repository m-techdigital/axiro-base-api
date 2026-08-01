<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceReview extends Model
{
    protected $fillable = ['transaction_id', 'product_id', 'reviewer_customer_id', 'reviewee_customer_id', 'rating', 'criteria', 'comment', 'status', 'moderation_note', 'moderated_by', 'moderated_at'];

    protected $casts = ['criteria' => 'array', 'rating' => 'integer', 'moderated_at' => 'datetime'];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(Customer::class, 'reviewer_customer_id');
    }

    public function reviewee()
    {
        return $this->belongsTo(Customer::class, 'reviewee_customer_id');
    }
}
