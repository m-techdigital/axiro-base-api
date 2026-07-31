<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
class CustomerProfileController extends Controller {
 public function update(Request $r){$c=auth('customer_api')->user();$d=$r->validate(['name'=>'required|string|max:255','phone'=>'nullable|string|max:30|unique:customers,phone,'.$c->id,'avatar_url'=>'nullable|string|max:2048']);$c->update($d);return success_response($c->fresh());}
}
