<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class TransactionActionRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['seller_handover', 'buyer_receive', 'renter_return', 'lessor_receive_return', 'complete', 'accept_terms', 'reject_terms'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
