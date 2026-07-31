<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\ApiFormRequest;

class VerifyEmailChangeRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['token' => ['required', 'string']];
    }
}
