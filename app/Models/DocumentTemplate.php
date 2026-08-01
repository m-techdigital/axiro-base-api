<?php

namespace App\Models;

use App\Models\Concerns\TracksAuditHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentTemplate extends Model
{
    use SoftDeletes;
    use TracksAuditHistory;

    protected $fillable = ['code', 'name', 'type', 'target_module', 'status', 'version', 'merge_fields', 'content_html', 'description', 'created_by', 'updated_by'];

    protected $casts = ['merge_fields' => 'array', 'version' => 'integer'];

    public function generatedDocuments()
    {
        return $this->hasMany(GeneratedDocument::class);
    }
}
