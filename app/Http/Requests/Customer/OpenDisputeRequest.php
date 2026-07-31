<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class OpenDisputeRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['reason' => ['required', Rule::in(['not_as_described', 'cannot_login', 'recalled', 'late_handover', 'damage', 'other'])], 'description' => ['required', 'string', 'max:5000'], 'evidence' => ['nullable', 'array'], 'evidence.*' => ['string', 'max:2048']];
    }
}
