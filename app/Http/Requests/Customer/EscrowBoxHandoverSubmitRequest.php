<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\ApiFormRequest;

class EscrowBoxHandoverSubmitRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['expected_version' => ['required', 'integer', 'min:1'], 'note' => ['required', 'string', 'max:2000']];
    }
}
