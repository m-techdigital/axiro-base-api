<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerSecurityToken extends Model
{
    protected $fillable = ['customer_id', 'purpose', 'token', 'payload', 'expires_at', 'used_at'];

    protected $casts = ['payload' => 'array', 'expires_at' => 'datetime', 'used_at' => 'datetime'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
