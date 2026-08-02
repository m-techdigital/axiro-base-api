<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class TransactionActionRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $deductionAmount = $this->input('rental_deposit_deduction_amount', '0.00');
        $deductionAmount = is_numeric($deductionAmount) ? (string) $deductionAmount : '0.00';

        return [
            'action' => ['required', Rule::in(['force_handover', 'force_return', 'complete', 'cancel', 'reopen'])],
            'note' => ['nullable', 'string', 'max:2000'],
            'rental_deposit_deduction_amount' => ['nullable', 'numeric', 'min:0'],
            'rental_deposit_deduction_note' => [
                'nullable',
                'string',
                'max:2000',
                Rule::requiredIf(fn () => bccomp($deductionAmount, '0.00', 2) > 0),
            ],
        ];
    }
}
