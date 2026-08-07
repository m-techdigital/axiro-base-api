<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EscrowBoxAgreementVersion extends Model
{
    protected $fillable = [
        'escrow_box_id',
        'version',
        'terms',
        'changed_by_side',
        'changed_by_customer_id',
        'changed_by_user_id',
        'change_note',
    ];

    protected $casts = ['terms' => 'array'];
}
