<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class TransactionCreateRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'purchase_mode' => ['nullable', Rule::in(['full', 'deposit', 'installment'])], 'initial_payment_amount' => ['nullable', 'numeric', 'min:0'], 'installment_count' => ['nullable', 'integer', 'min:2', 'max:12'],
            'rental_rate_id' => ['nullable', 'exists:listing_rental_rates,id'], 'rental_period' => ['nullable', 'integer', 'min:1'], 'rental_period_unit' => ['nullable', Rule::in(['hour', 'day', 'week', 'month'])],
            'rental_period_count' => ['nullable', 'integer', 'min:1', 'max:365'], 'rental_billing_mode' => ['nullable', Rule::in(['upfront', 'periodic'])], 'rental_billing_cycle_unit' => ['nullable', Rule::in(['hour', 'day', 'week', 'month'])],
            'rental_billing_cycle_count' => ['nullable', 'integer', 'min:1', 'max:365'], 'installment_interval_unit' => ['nullable', Rule::in(['day', 'week', 'month'])], 'installment_interval_count' => ['nullable', 'integer', 'min:1', 'max:90'],
            'rental_start_at' => ['nullable', 'date'], 'rental_end_at' => ['nullable', 'date', 'after:rental_start_at'], 'due_date' => ['nullable', 'date'], 'payment_method' => ['nullable', Rule::in(['wallet', 'bank', 'momo', 'card'])], 'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
