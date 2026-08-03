<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;

class MarkWithdrawalPaidRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'payment_reference' => ['required', 'string', 'max:150'],
            'proof_url' => ['nullable', 'string', 'max:500'],
        ];
    }
}
