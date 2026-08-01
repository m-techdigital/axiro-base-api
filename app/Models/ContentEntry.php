<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentEntry extends Model
{
    protected $fillable = ['type', 'slug', 'title', 'summary', 'body', 'version', 'status', 'requires_acceptance', 'effective_at', 'published_at', 'metadata', 'created_by', 'updated_by'];

    protected $casts = ['version' => 'integer', 'requires_acceptance' => 'boolean', 'effective_at' => 'datetime', 'published_at' => 'datetime', 'metadata' => 'array'];
}
