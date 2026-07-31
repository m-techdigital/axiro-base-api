<?php
namespace App\Http\Controllers;
use App\Models\GeneratedDocument;
use App\Models\Transaction;
use App\Services\Documents\MarketplaceDocumentService;
use Illuminate\Http\Request;
class GeneratedDocumentController extends Controller
{
    public function index(Request $request){$q=GeneratedDocument::with(['template:id,code,name,type','transaction:id,code,status','acceptances.customer:id,code,name'])->when($request->transaction_id,fn($q,$v)=>$q->where('transaction_id',$v))->when($request->document_type,fn($q,$v)=>$q->where('document_type',$v))->latest();$p=$q->paginate($request->integer('per_page',20));return success_response($p->items(),'Thành công',200,['pagination'=>['current_page'=>$p->currentPage(),'last_page'=>$p->lastPage(),'per_page'=>$p->perPage(),'total'=>$p->total()]]);}
    public function show(GeneratedDocument $generatedDocument){return success_response($generatedDocument->load(['template','transaction.product','transaction.buyer:id,code,name','transaction.seller:id,code,name','acceptances.customer:id,code,name']));}
    public function generate(Request $request,Transaction $transaction,MarketplaceDocumentService $service){$data=$request->validate(['document_type'=>'required|string','regenerate'=>'nullable|boolean']);return success_response($service->generate($transaction,$data['document_type'],user_id(),(bool)($data['regenerate']??false)),'Đã tạo tài liệu',201);}
    public function ensure(Transaction $transaction,MarketplaceDocumentService $service){return success_response($service->ensureForTransaction($transaction));}
    public function preview(GeneratedDocument $generatedDocument){return success_response(['id'=>$generatedDocument->id,'title'=>$generatedDocument->title,'html'=>$generatedDocument->rendered_html,'status'=>$generatedDocument->status,'version'=>$generatedDocument->version]);}
    public function download(GeneratedDocument $generatedDocument,MarketplaceDocumentService $service){return response($service->pdf($generatedDocument),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="'.str($generatedDocument->code)->slug().'.pdf"']);}
}
