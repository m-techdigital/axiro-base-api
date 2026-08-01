<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerRefreshToken extends Model
{
    protected $fillable = ['customer_id', 'token', 'ip_address', 'user_agent', 'last_used_at', 'expires_at', 'revoked_at'];

    protected $casts = ['last_used_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
