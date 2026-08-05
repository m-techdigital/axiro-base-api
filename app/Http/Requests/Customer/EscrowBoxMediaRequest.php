<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\ApiFormRequest;

class EscrowBoxMediaRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'min:1', 'max:10'],
            'images.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'handover_step_id' => ['nullable', 'integer'],
        ];
    }
}
