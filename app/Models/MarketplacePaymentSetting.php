<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplacePaymentSetting extends Model
{
    protected $fillable = ['bank_id', 'bank_name', 'account_no', 'account_name', 'qr_template', 'transfer_prefix', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
