<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\ApiFormRequest;

class EscrowBoxCounterpartyResolveRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => preg_replace('/\D+/', '', (string) $this->input('phone')),
        ]);
    }

    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:1'],
            'phone' => ['required', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'expected_version' => 'phiên bản Box',
            'phone' => 'số điện thoại Bên B',
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'Vui lòng nhập số điện thoại Bên B.',
        ];
    }
}
