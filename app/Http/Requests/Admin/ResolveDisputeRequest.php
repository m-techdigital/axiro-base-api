<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class ResolveDisputeRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['resolved', 'rejected'])],
            'resolution' => ['required', 'string', 'min:10', 'max:5000'],
            'outcome' => ['required', Rule::in(['complete', 'cancel_refund', 'cancel_no_refund', 'reopen'])],
        ];
    }
}
