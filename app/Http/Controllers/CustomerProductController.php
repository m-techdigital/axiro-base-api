<?php
namespace App\Http\Controllers;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
class CustomerProductController extends Controller {
 public function index(Request $r){return success_response(Product::where('owner_customer_id',auth('customer_api')->id())->latest()->paginate($r->integer('per_page',20)));}
 public function store(Request $r){$d=$this->validated($r);$d['code']=$d['code']??'PRD-'.strtoupper(Str::random(8));$d['slug']=$d['slug']??Str::slug($d['name'].'-'.$d['code']);$d['owner_customer_id']=auth('customer_api')->id();return success_response(Product::create($d),'Đã tạo',201);}
 public function update(Request $r,Product $product){abort_unless($product->owner_customer_id===auth('customer_api')->id(),403);$product->update($this->validated($r,true));return success_response($product->fresh());}
 private function validated(Request $r,bool $partial=false):array{$req=$partial?'sometimes':'required';return $r->validate(['code'=>'nullable|string|max:50','name'=>"$req|string|max:255",'product_type'=>"$req|string|max:50",'game_code'=>"$req|string|max:50",'server_name'=>'nullable|string|max:100','level'=>'nullable|integer|min:0','status'=>'nullable|in:active,inactive','price'=>'nullable|numeric|min:0','description'=>'nullable|string','image_url'=>'nullable|string|max:2048','image_urls'=>'nullable|array','image_urls.*'=>'string|max:2048','attributes'=>'nullable|array']);}
}
