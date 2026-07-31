<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class RequestEmailChangeRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $customerId = auth('customer_api')->id();

        return [
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('customers', 'email')->ignore($customerId),
            ],
        ];
    }
}
