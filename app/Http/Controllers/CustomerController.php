<?php
namespace App\Http\Controllers;
use App\Models\Customer;
use App\Models\CustomerWallet;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
class CustomerController extends Controller {
 public function index(Request $r){$q=Customer::with('wallet')->when($r->keyword,fn($q,$v)=>$q->where(fn($x)=>$x->where('name','like',"%$v%")->orWhere('username','like',"%$v%")->orWhere('phone','like',"%$v%")))->when($r->status,fn($q,$v)=>$q->where('status',$v))->latest();return success_response($q->paginate(min(100,max(1,$r->integer('per_page',20)))));}
 public function store(Request $r){$d=$this->validated($r);$d['code']=$d['code']??'CUS-'.strtoupper(Str::random(8));$m=Customer::create($d);CustomerWallet::firstOrCreate(['customer_id'=>$m->id]);return success_response($m->load('wallet'),'Đã tạo',201);}
 public function show(Customer $customer){return success_response($customer->load(['wallet','products','listings']));}
 public function update(Request $r,Customer $customer){$customer->update($this->validated($r,$customer));return success_response($customer->fresh('wallet'));}
 public function destroy(Customer $customer){abort_if($customer->purchases()->exists()||$customer->sales()->exists(),409,'Khách hàng đã có giao dịch.');$customer->delete();return success_response();}
 private function validated(Request $r,?Customer $c=null):array{return $r->validate(['code'=>['nullable','string','max:50',Rule::unique('customers')->ignore($c?->id)],'username'=>['required','string','max:80',Rule::unique('customers')->ignore($c?->id)],'name'=>'required|string|max:255','email'=>['nullable','email',Rule::unique('customers')->ignore($c?->id)],'phone'=>['nullable','string|max:30',Rule::unique('customers')->ignore($c?->id)],'password'=>[$c?'nullable':'required','string','min:8'],'status'=>'required|in:active,inactive,blocked','avatar_url'=>'nullable|string|max:2048']);}
}
