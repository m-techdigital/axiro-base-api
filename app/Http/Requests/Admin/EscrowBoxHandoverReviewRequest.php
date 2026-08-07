<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;

class EscrowBoxHandoverReviewRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['action' => ['required', 'in:verify,request_more,reject'], 'expected_version' => ['required', 'integer', 'min:1'], 'note' => ['nullable', 'required_unless:action,verify', 'string', 'max:2000']];
    }
}
