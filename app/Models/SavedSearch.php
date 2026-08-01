<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedSearch extends Model
{
    protected $fillable = ['customer_id', 'name', 'filters', 'notify', 'last_notified_at'];

    protected $casts = ['filters' => 'array', 'notify' => 'boolean', 'last_notified_at' => 'datetime'];
}
