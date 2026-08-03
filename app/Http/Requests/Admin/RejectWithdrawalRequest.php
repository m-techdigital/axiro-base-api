<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;

class RejectWithdrawalRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['note' => ['required', 'string', 'max:2000']];
    }
}
