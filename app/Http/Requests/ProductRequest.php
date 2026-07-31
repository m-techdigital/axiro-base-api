<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class ProductRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array {
        $id = $this->route('product')?->id;
        return [
            'code'=>['required','string','max:50',Rule::unique('products','code')->ignore($id)],
            'name'=>['required','string','max:255'],
            'slug'=>['nullable','string','max:255',Rule::unique('products','slug')->ignore($id)],
            'product_type'=>['nullable','string','max:50'],
            'game_code'=>['nullable','string','max:50'],
            'server_name'=>['nullable','string','max:100'],
            'level'=>['nullable','integer','min:0'],
            'status'=>['required',Rule::in(['draft','active','inactive'])],
            'price'=>['required','numeric','min:0'],
            'description'=>['nullable','string'],
            'image_url'=>['nullable','string','max:2048'],
            'image_urls'=>['nullable','array'],
            'image_urls.*'=>['string','max:2048'],
            'attributes'=>['nullable','array'],
            'owner_customer_id'=>['nullable','integer','exists:customers,id'],
        ];
    }
}
