<?php

namespace App\Models;

use App\Models\Concerns\TracksAuditHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentTemplate extends Model
{
    use SoftDeletes;
    use TracksAuditHistory;

    protected $fillable = ['code', 'name', 'type', 'target_module', 'status', 'version', 'supersedes_template_id', 'merge_fields', 'content_html', 'description', 'published_at', 'deprecated_at', 'created_by', 'updated_by'];

    protected $casts = ['merge_fields' => 'array', 'version' => 'integer', 'published_at' => 'datetime', 'deprecated_at' => 'datetime'];

    public function generatedDocuments()
    {
        return $this->hasMany(GeneratedDocument::class);
    }

    public function supersedes()
    {
        return $this->belongsTo(self::class, 'supersedes_template_id');
    }

    public function revisions()
    {
        return $this->hasMany(self::class, 'supersedes_template_id');
    }

    public function successors()
    {
        return $this->revisions();
    }
}
