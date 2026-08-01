<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('transaction')?->id;

        return ['code' => ['required', 'string', 'max:50', Rule::unique('transactions', 'code')->ignore($id)], 'transaction_type' => ['required', Rule::in(['purchase', 'rental'])], 'product_id' => ['required', 'integer', 'exists:products,id'], 'buyer_customer_id' => ['required', 'integer', 'exists:customers,id'], 'seller_customer_id' => ['required', 'integer', 'different:buyer_customer_id', 'exists:customers,id'], 'transaction_value' => ['required', 'numeric', 'min:0'], 'service_fee' => ['nullable', 'numeric', 'min:0'], 'discount' => ['nullable', 'numeric', 'min:0'], 'deposit_amount' => ['nullable', 'numeric', 'min:0'], 'paid_amount' => ['nullable', 'numeric', 'min:0'], 'refunded_amount' => ['nullable', 'numeric', 'min:0'], 'transaction_date' => ['required', 'date'], 'due_date' => ['nullable', 'date', 'after_or_equal:transaction_date'], 'rental_start_at' => ['nullable', 'required_if:transaction_type,rental', 'date'], 'rental_end_at' => ['nullable', 'required_if:transaction_type,rental', 'date', 'after:rental_start_at'], 'status' => ['required', 'string', 'max:30'], 'payment_method' => ['nullable', 'string', 'max:50'], 'note' => ['nullable', 'string']];
    }

    public function after(): array
    {
        return [function ($v) {
            $total = (float) $this->transaction_value + (float) ($this->service_fee ?? 0) + (float) ($this->deposit_amount ?? 0) - (float) ($this->discount ?? 0);
            if ($total < 0) {
                $v->errors()->add('discount', 'Discount cannot make total payable negative.');
            }if ((float) ($this->paid_amount ?? 0) > $total) {
                $v->errors()->add('paid_amount', 'Paid amount cannot exceed total payable.');
            }
        }];
    }
}
