<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class DepositRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['amount' => ['required', 'numeric', 'min:10000', 'max:100000000'], 'payment_method' => ['required', Rule::in(['bank', 'momo'])], 'note' => ['nullable', 'string', 'max:1000']];
    }

    public function attributes(): array
    {
        return ['amount' => 'số tiền', 'payment_method' => 'phương thức thanh toán', 'note' => 'ghi chú'];
    }
}
