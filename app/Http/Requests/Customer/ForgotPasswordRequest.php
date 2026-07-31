<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\ApiFormRequest;

class ForgotPasswordRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['login' => ['required', 'string', 'max:255']];
    }
}
