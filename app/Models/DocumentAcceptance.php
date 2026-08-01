<?php

namespace App\Models;

use App\Models\Concerns\TracksAuditHistory;
use Illuminate\Database\Eloquent\Model;

class DocumentAcceptance extends Model
{
    use TracksAuditHistory;

    protected $fillable = ['generated_document_id', 'customer_id', 'role', 'status', 'accepted_at', 'ip_address', 'user_agent', 'metadata'];

    protected $casts = ['accepted_at' => 'datetime', 'metadata' => 'array'];

    public function document()
    {
        return $this->belongsTo(GeneratedDocument::class, 'generated_document_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
