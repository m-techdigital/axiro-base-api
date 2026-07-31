<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class SubmitPaymentRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['payment_method' => ['required', Rule::in(['wallet', 'bank', 'momo', 'card'])], 'reference' => ['nullable', 'string', 'max:150'], 'note' => ['nullable', 'string', 'max:1000']];
    }
}
