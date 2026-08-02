<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;

class ManualReleaseProductHoldRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'min:10', 'max:2000'],
            'expected_version' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'note' => 'ghi chú nhả giữ chỗ',
            'expected_version' => 'phiên bản trạng thái sản phẩm',
        ];
    }
}
