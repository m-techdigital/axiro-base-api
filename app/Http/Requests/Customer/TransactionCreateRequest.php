<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\ApiFormRequest;
use App\Models\Product;
use Illuminate\Validation\Rule;

class TransactionCreateRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('transaction_type')) {
            return;
        }

        $product = $this->route('product');
        if (! $product instanceof Product) {
            return;
        }

        $supportsSell = $product->supports('sale');
        $supportsRent = $product->supports('rental');

        if ($supportsSell && ! $supportsRent) {
            $this->merge(['transaction_type' => 'sale']);
        } elseif ($supportsRent && ! $supportsSell) {
            $this->merge(['transaction_type' => 'rental']);
        }
    }

    public function rules(): array
    {
        return [
            'purchase_mode' => ['nullable', Rule::in(['full', 'deposit', 'installment'])], 'initial_payment_amount' => ['nullable', 'numeric', 'min:0'], 'installment_count' => ['nullable', 'integer', 'min:2', 'max:12'],
            'transaction_type' => ['required', Rule::in(['sale', 'rental'])], 'rental_rate_id' => ['nullable', 'exists:product_rental_rates,id'], 'rental_period' => ['nullable', 'integer', 'min:1'], 'rental_period_unit' => ['nullable', Rule::in(['hour', 'day', 'week', 'month'])],
            'rental_period_count' => ['nullable', 'integer', 'min:1', 'max:365'], 'rental_billing_mode' => ['nullable', Rule::in(['upfront', 'periodic'])], 'rental_billing_cycle_unit' => ['nullable', Rule::in(['hour', 'day', 'week', 'month'])],
            'rental_billing_cycle_count' => ['nullable', 'integer', 'min:1', 'max:365'], 'installment_interval_unit' => ['nullable', Rule::in(['day', 'week', 'month'])], 'installment_interval_count' => ['nullable', 'integer', 'min:1', 'max:90'],
            'rental_start_at' => ['nullable', 'date'], 'rental_end_at' => ['nullable', 'date', 'after:rental_start_at'], 'due_date' => ['nullable', 'date'], 'payment_method' => ['nullable', Rule::in(['wallet', 'bank', 'momo', 'card'])], 'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
