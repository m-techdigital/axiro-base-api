<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\ApiFormRequest;

class DepositProofRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['proof' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'], 'external_reference' => ['nullable', 'string', 'max:150'], 'note' => ['nullable', 'string', 'max:1000']];
    }

    public function attributes(): array
    {
        return ['proof' => 'ảnh biên nhận', 'external_reference' => 'mã giao dịch ngân hàng', 'note' => 'ghi chú'];
    }
}
