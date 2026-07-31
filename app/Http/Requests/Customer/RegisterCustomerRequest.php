<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\ApiFormRequest;
use App\Support\SecurityPasswordRules;

class RegisterCustomerRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:80', 'unique:customers,username'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:customers,email'],
            'phone' => ['nullable', 'string', 'max:30', 'unique:customers,phone'],
            'password' => ['required', 'confirmed', SecurityPasswordRules::make()],
        ];
    }
}
