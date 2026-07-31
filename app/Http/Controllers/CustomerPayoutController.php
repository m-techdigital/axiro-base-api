<?php
namespace App\Http\Controllers;
use App\Models\{CustomerPayoutAccount,CustomerVerification,WithdrawalRequest};
use App\Services\Payouts\WithdrawalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class CustomerPayoutController extends Controller {
 public function overview(){ $id=auth('customer_api')->id(); return success_response(['verification'=>CustomerVerification::firstOrCreate(['customer_id'=>$id],['status'=>'unverified']),'accounts'=>CustomerPayoutAccount::where('customer_id',$id)->latest()->get(),'withdrawals'=>WithdrawalRequest::with('payoutAccount')->where('customer_id',$id)->latest()->paginate(20)]); }
 public function submitVerification(Request $r){$d=$r->validate(['document_type'=>'required|in:citizen_id,passport','document_number'=>'required|string|max:80','document_front_url'=>'required|string|max:500','document_back_url'=>'nullable|string|max:500','selfie_url'=>'required|string|max:500']);$id=auth('customer_api')->id();$item=CustomerVerification::firstOrCreate(['customer_id'=>$id]);if($item->status==='verified')return success_response($item);$item->update([...$d,'status'=>'pending','submitted_at'=>now(),'verified_at'=>null,'verified_by'=>null,'review_note'=>null]);return success_response($item->fresh(),'Đã gửi hồ sơ xác minh.');}
 public function storeAccount(Request $r){$d=$r->validate(['bank_code'=>'required|string|max:30','bank_name'=>'required|string|max:120','account_name'=>'required|string|max:150','account_number'=>'required|string|max:80','is_default'=>'sometimes|boolean']);$id=auth('customer_api')->id();return DB::transaction(function()use($d,$id){if(!empty($d['is_default']))CustomerPayoutAccount::where('customer_id',$id)->update(['is_default'=>false]);$item=CustomerPayoutAccount::create([...$d,'customer_id'=>$id,'status'=>'pending']);return success_response($item,'Đã thêm tài khoản nhận tiền.',201);});}
 public function withdraw(Request $r,WithdrawalService $service){$d=$r->validate(['payout_account_id'=>'required|integer','amount'=>'required|numeric|min:50000','note'=>'nullable|string|max:1000','idempotency_key'=>'nullable|string|max:150']);return success_response($service->submit(auth('customer_api')->id(),(int)$d['payout_account_id'],(string)$d['amount'],$d['note']??null,$d['idempotency_key']??null),'Đã gửi yêu cầu rút tiền.',201);}
}
