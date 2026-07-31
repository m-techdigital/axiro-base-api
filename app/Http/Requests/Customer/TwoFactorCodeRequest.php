<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\ApiFormRequest;

class TwoFactorCodeRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['code' => ['required', 'string', 'max:20']];
    }
}
