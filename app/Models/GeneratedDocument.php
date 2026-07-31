<?php
namespace App\Models;
use App\Models\Concerns\TracksAuditHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class GeneratedDocument extends Model
{
    use TracksAuditHistory;
    use SoftDeletes;
    protected $fillable=['code','document_template_id','transaction_id','contract_id','document_type','status','version','title','merge_payload','rendered_html','issued_at','issued_by'];
    protected $casts=['merge_payload'=>'array','issued_at'=>'datetime','version'=>'integer'];
    public function template(){return $this->belongsTo(DocumentTemplate::class,'document_template_id');}
    public function transaction(){return $this->belongsTo(Transaction::class);}
    public function contract(){return $this->belongsTo(Contract::class);}
    public function acceptances(){return $this->hasMany(DocumentAcceptance::class);}
}
