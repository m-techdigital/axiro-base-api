<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\ApiFormRequest;

class EscrowBoxCounterpartyInviteRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:1'],
            'candidate_token' => ['required', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'expected_version' => 'phiên bản Box',
            'candidate_token' => 'khách hàng Bên B đã chọn',
        ];
    }

    public function messages(): array
    {
        return [
            'candidate_token.required' => 'Vui lòng tìm và chọn khách hàng Bên B trước khi gửi lời mời.',
        ];
    }
}
