<?php

namespace App\Http\Requests;

use App\Enums\OfferModeCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $modes = $this->input('offer_modes');
        if ($modes === null && is_array($this->input('transaction_types'))) {
            $legacy = $this->input('transaction_types', []);
            $modes = [];
            if (in_array('sale', $legacy, true) || in_array('sell', $legacy, true)) {
                $modes[] = 'sell';
            }
            if (in_array('rental', $legacy, true) || in_array('rent', $legacy, true)) {
                $modes[] = 'rent';
            }
            $this->merge(['installment_enabled' => $this->boolean('installment_enabled') || in_array('installment', $legacy, true)]);
        }

        $this->merge(['offer_modes' => array_values(array_unique($modes ?? []))]);
    }

    public function rules(): array
    {
        $id = $this->route('product')?->id;

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('products', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($id)],
            'product_type' => ['required', 'string', 'max:50'],
            'game_code' => ['required', 'string', 'max:50'],
            'server_name' => ['nullable', 'string', 'max:100'],
            'level' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'inactive', 'suspended', 'archived'])],
            'offer_modes' => ['required', 'array', 'min:1'],
            'offer_modes.*' => ['required', Rule::enum(OfferModeCode::class)],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'sale_deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'installment_enabled' => ['boolean'],
            'max_installment_count' => ['nullable', 'required_if:installment_enabled,1', 'integer', 'min:2', 'max:12'],
            'minimum_initial_payment' => ['nullable', 'required_if:installment_enabled,1', 'numeric', 'min:0'],
            'installment_interval_unit' => ['nullable', Rule::in(['day', 'week', 'month'])],
            'installment_interval_count' => ['nullable', 'integer', 'min:1', 'max:90'],
            'rental_price' => ['nullable', 'numeric', 'min:0'],
            'rental_price_unit' => ['nullable', Rule::in(['hour', 'day', 'week', 'month'])],
            'minimum_rental_period' => ['nullable', 'integer', 'min:1'],
            'rental_period_unit' => ['nullable', Rule::in(['hour', 'day', 'week', 'month'])],
            'rental_billing_mode' => ['nullable', Rule::in(['upfront', 'periodic'])],
            'rental_billing_cycle_unit' => ['nullable', Rule::in(['hour', 'day', 'week', 'month'])],
            'rental_billing_cycle_count' => ['nullable', 'integer', 'min:1', 'max:365'],
            'rental_deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'rental_rates' => ['nullable', 'array', 'max:20'],
            'rental_rates.*.label' => ['required_with:rental_rates', 'string', 'max:100'],
            'rental_rates.*.period_unit' => ['required_with:rental_rates', Rule::in(['hour', 'day', 'week', 'month'])],
            'rental_rates.*.period_count' => ['required_with:rental_rates', 'integer', 'min:1', 'max:365'],
            'rental_rates.*.price' => ['required_with:rental_rates', 'numeric', 'min:0'],
            'rental_rates.*.deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'rental_rates.*.is_default' => ['nullable', 'boolean'],
            'rental_rates.*.is_active' => ['nullable', 'boolean'],
            'available_from' => ['nullable', 'date'],
            'available_until' => ['nullable', 'date', 'after:available_from'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'image_urls' => ['nullable', 'array'],
            'image_urls.*' => ['string', 'max:2048'],
            'attributes' => ['nullable', 'array'],
            'owner_customer_id' => ['nullable', 'integer', 'exists:customers,id'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $modes = $this->input('offer_modes', []);
            if ($this->boolean('installment_enabled') && ! in_array('sell', $modes, true)) {
                $validator->errors()->add('installment_enabled', 'Trả góp chỉ được bật khi sản phẩm có mục đích bán.');
            }
            if (in_array('sell', $modes, true) && ! $this->filled('sale_price')) {
                $validator->errors()->add('sale_price', 'Giá bán là bắt buộc khi bật mục đích bán.');
            }
            if (in_array('rent', $modes, true) && ! $this->filled('rental_price') && empty($this->input('rental_rates'))) {
                $validator->errors()->add('rental_price', 'Cần khai báo giá thuê hoặc ít nhất một gói thuê.');
            }
        }];
    }
}
