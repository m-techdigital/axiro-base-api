<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\ApiFormRequest;

class DisableTwoFactorRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
            'code' => ['required', 'string', 'max:20'],
        ];
    }
}
