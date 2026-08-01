<?php

namespace App\Models;

use App\Models\Concerns\TracksAuditHistory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class Customer extends Authenticatable implements JWTSubject
{
    use HasFactory, SoftDeletes;
    use TracksAuditHistory;

    protected $fillable = ['code', 'username', 'name', 'email', 'phone', 'password', 'status', 'avatar_url', 'last_login_at', 'last_login_ip', 'email_verified_at', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected $casts = ['password' => 'hashed', 'last_login_at' => 'datetime', 'email_verified_at' => 'datetime', 'two_factor_confirmed_at' => 'datetime'];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return ['actor_type' => 'customer'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'owner_customer_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Transaction::class, 'buyer_customer_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Transaction::class, 'seller_customer_id');
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(CustomerWallet::class);
    }
}
