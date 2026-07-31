<?php
namespace App\Http\Controllers;
use App\Models\MarketplaceDispute;
use App\Services\Marketplace\TransactionLifecycleService;
use Illuminate\Http\Request;
class DisputeController extends Controller {
 public function index(Request $r){$q=MarketplaceDispute::with(['transaction.product','openedBy:id,code,name'])->when($r->status,fn($q,$v)=>$q->where('status',$v))->latest();return success_response($q->paginate(min(100,max(1,$r->integer('per_page',20)))));}
 public function show(MarketplaceDispute $dispute){return success_response($dispute->load(['transaction.product','transaction.payments','transaction.events','openedBy:id,code,name']));}
 public function resolve(Request $r,MarketplaceDispute $dispute,TransactionLifecycleService $service){$d=$r->validate(['status'=>'required|in:resolved,rejected','resolution'=>'required|string|max:5000','transaction_status'=>'nullable|in:completed,cancelled,paid,returned']);return success_response($service->resolveDispute($dispute,user_id(),$d));}
}
