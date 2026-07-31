<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\ApiFormRequest;

class VerifyTwoFactorRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'challenge_token' => ['required', 'string'],
            'code' => ['required', 'string', 'max:20'],
        ];
    }
}
