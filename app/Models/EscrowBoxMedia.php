<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EscrowBoxMedia extends Model
{
    protected $fillable = ['escrow_box_id', 'handover_step_id', 'party_side', 'uploaded_by_customer_id', 'disk', 'path', 'thumbnail_path', 'mime', 'size_bytes', 'width', 'height', 'checksum', 'status', 'retention_locked_until'];

    protected $hidden = ['path', 'thumbnail_path', 'checksum'];

    protected $casts = ['retention_locked_until' => 'datetime'];
}
