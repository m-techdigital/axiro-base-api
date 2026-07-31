<?php
namespace App\Http\Controllers;
use App\Models\ProductListing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class CustomerListingController extends Controller {
 public function index(Request $r){return success_response(ProductListing::with(['product','rentalRates'])->where('owner_customer_id',auth('customer_api')->id())->latest()->paginate($r->integer('per_page',20)));}
 public function store(Request $r){$d=$this->validated($r);return DB::transaction(function()use($d){$rates=$d['rental_rates']??[];unset($d['rental_rates']);$d['code']='LST-'.strtoupper(Str::random(10));$d['owner_customer_id']=auth('customer_api')->id();$d['status']='pending_review';$listing=ProductListing::create($d);$this->syncRates($listing,$rates);return success_response($listing->load(['product','rentalRates']),'Đã tạo',201);});}
 public function update(Request $r,ProductListing $listing){abort_unless($listing->owner_customer_id===auth('customer_api')->id(),403);abort_if(in_array($listing->status,['reserved','transacting','completed'],true),422,'Không thể sửa tin đăng đang có giao dịch.');$d=$this->validated($r,true);return DB::transaction(function()use($listing,$d){$rates=$d['rental_rates']??null;unset($d['rental_rates']);$d['status']='pending_review';$d['approved_at']=null;$d['approved_by']=null;$d['rejection_reason']=null;$listing->update($d);if($rates!==null)$this->syncRates($listing,$rates);return success_response($listing->fresh()->load(['product','rentalRates']));});}
 private function syncRates(ProductListing $listing,array $rates):void{$listing->rentalRates()->delete();foreach(array_values($rates) as $i=>$rate)$listing->rentalRates()->create($rate+['sort_order'=>$i]);}
 private function validated(Request $r,bool $partial=false):array{$req=$partial?'sometimes':'required';return $r->validate([
  'product_id'=>"$req|exists:products,id",'listing_type'=>"$req|in:sale,rental",'title'=>"$req|string|max:255",'description'=>'nullable|string',
  'sale_price'=>'nullable|required_if:listing_type,sale|numeric|min:0','rental_price'=>'nullable|required_if:listing_type,rental|numeric|min:0',
  'rental_price_unit'=>'nullable|required_if:listing_type,rental|in:hour,day,week,month','minimum_rental_period'=>'nullable|integer|min:1',
  'rental_period_unit'=>'nullable|in:hour,day,week,month','rental_billing_mode'=>'nullable|in:upfront,periodic','rental_billing_cycle_unit'=>'nullable|in:hour,day,week,month','rental_billing_cycle_count'=>'nullable|integer|min:1|max:365',
  'deposit_amount'=>'nullable|numeric|min:0','allow_installment'=>'nullable|boolean','max_installment_count'=>'nullable|required_if:allow_installment,1|integer|min:2|max:12','minimum_initial_payment'=>'nullable|required_if:allow_installment,1|numeric|min:0',
  'installment_interval_unit'=>'nullable|in:day,week,month','installment_interval_count'=>'nullable|integer|min:1|max:90',
  'rental_rates'=>'nullable|array|max:20','rental_rates.*.label'=>'required_with:rental_rates|string|max:100','rental_rates.*.period_unit'=>'required_with:rental_rates|in:hour,day,week,month','rental_rates.*.period_count'=>'required_with:rental_rates|integer|min:1|max:365','rental_rates.*.price'=>'required_with:rental_rates|numeric|min:0','rental_rates.*.deposit_amount'=>'nullable|numeric|min:0','rental_rates.*.is_default'=>'nullable|boolean','rental_rates.*.is_active'=>'nullable|boolean',
  'available_from'=>'nullable|date','available_until'=>'nullable|date|after:available_from'
 ]);}
}
