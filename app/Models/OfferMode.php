<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class OfferMode extends Model
{
    protected $fillable = ['code', 'name', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean'];

    public function products(): MorphToMany
    {
        return $this->morphedByMany(Product::class, 'model', 'model_offer_modes');
    }
}
