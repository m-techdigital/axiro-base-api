<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\ApiFormRequest;
use App\Support\SecurityPasswordRules;

class ChangePasswordRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', SecurityPasswordRules::make()],
        ];
    }
}
