<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\ApiFormRequest;

class CreateWithdrawalRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'payout_account_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:50000'],
            'note' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:150'],
        ];
    }
}
