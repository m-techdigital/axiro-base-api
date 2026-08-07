<?php

namespace App\Http\Requests\Concerns;

trait HasEscrowBoxValidationAttributes
{
    public function attributes(): array
    {
        return [
            'deal_type' => 'loại giao dịch',
            'party_a_asset.type' => 'loại tài sản Bên A',
            'party_a_asset.title' => 'tên tài sản Bên A',
            'party_a_asset.description' => 'mô tả tài sản Bên A',
            'party_a_asset.reference_value' => 'giá trị tham chiếu Bên A',
            'party_a_asset.delivery_method' => 'phương thức bàn giao Bên A',
            'party_b_asset.type' => 'loại tài sản Bên B',
            'party_b_asset.title' => 'tên tài sản Bên B',
            'party_b_asset.description' => 'mô tả tài sản Bên B',
            'party_b_asset.reference_value' => 'giá trị tham chiếu Bên B',
            'party_b_asset.delivery_method' => 'phương thức bàn giao Bên B',
            'topup_payer_side' => 'bên bù tiền',
            'topup_amount' => 'số tiền bù',
            'fee_payer_mode' => 'bên chịu phí',
            'inspection_period_minutes' => 'thời gian kiểm tra',
            'success_conditions' => 'điều kiện thành công',
            'cancellation_conditions' => 'điều kiện hủy',
            'additional_terms' => 'điều khoản bổ sung',
            'change_note' => 'lý do thay đổi',
            'expires_in_hours' => 'thời hạn link mời',
        ];
    }

    public function messages(): array
    {
        return [
            'topup_amount.required' => 'Vui lòng nhập số tiền bù.',
            'topup_amount.numeric' => 'Số tiền bù phải là số hợp lệ.',
            'topup_amount.min' => 'Số tiền bù phải tối thiểu 1.000 đ.',
            'topup_payer_side.required' => 'Vui lòng chọn bên bù tiền.',
            'inspection_period_minutes.min' => 'Thời gian kiểm tra phải tối thiểu 15 phút.',
            'inspection_period_minutes.max' => 'Thời gian kiểm tra không được vượt quá 1.440 phút.',
        ];
    }
}
