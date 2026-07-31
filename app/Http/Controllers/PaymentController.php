<?php
namespace App\Http\Controllers;
use App\Models\TransactionPayment;
use App\Services\Marketplace\MarketplaceNotificationService;
use App\Services\Marketplace\TransactionLifecycleService;
use Illuminate\Http\Request;
class PaymentController extends Controller {
 public function index(Request $r){$q=TransactionPayment::with(['transaction.product','customer:id,code,name'])->when($r->status,fn($q,$v)=>$q->where('status',$v))->when($r->transaction_id,fn($q,$v)=>$q->where('transaction_id',$v))->latest();return success_response($q->paginate(min(100,max(1,$r->integer('per_page',20)))));}
 public function confirm(TransactionPayment $payment,TransactionLifecycleService $service){return success_response($service->confirmPayment($payment,user_id()));}
 public function reject(Request $r,TransactionPayment $payment,MarketplaceNotificationService $notifications){$d=$r->validate(['note'=>'required|string|max:2000']);$payment->update(['status'=>'rejected','note'=>$d['note']]);$payment->load('transaction');$notifications->transaction($payment->customer_id,'payment_rejected','Thanh toán cần gửi lại',$d['note'],$payment->transaction_id,$payment->transaction->code);return success_response($payment->fresh());}
}
