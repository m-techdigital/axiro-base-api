<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'audit_type', 'event_type', 'risk_level',
        'actor_type', 'actor_id', 'entity_type', 'entity_id',
        'context_type', 'context_id', 'request_id', 'correlation_id',
        'route_name', 'path', 'method', 'status_code',
        'title', 'description', 'old_values', 'new_values',
        'changed_fields', 'validation_errors', 'metadata',
        'ip_address', 'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'changed_fields' => 'array',
        'validation_errors' => 'array',
        'metadata' => 'array',
        'status_code' => 'integer',
    ];
}
