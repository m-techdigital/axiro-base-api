<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceRiskFlag extends Model
{
    protected $fillable = ['code', 'subject_type', 'subject_id', 'rule_code', 'level', 'status', 'reason', 'evidence', 'resolution', 'resolved_by', 'resolved_at'];

    protected $casts = ['evidence' => 'array', 'resolved_at' => 'datetime'];
}
