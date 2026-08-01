<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;

class ProductRejectRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:2000']];
    }

    public function attributes(): array
    {
        return ['reason' => 'lý do từ chối'];
    }
}
