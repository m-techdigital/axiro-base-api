<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;

class EscrowBoxReviewRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'action' => ['required', 'in:approve,request_changes,reject'],
            'expected_version' => ['required', 'integer', 'min:1'],
            'risk_level' => ['required', 'in:low,medium,high,blocked'],
            'review_note' => ['nullable', 'required_if:action,request_changes,reject', 'string', 'max:3000'],
            'handover_sequence' => ['nullable', 'required_if:action,approve', 'in:party_a_first,party_b_first,simultaneous_admin_observed'],
            'base_fee_override' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'percentage_rate_override' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fee_payer_override' => ['nullable', 'in:party_a,party_b,split_equal'],
            'fee_override_reason' => ['nullable', 'required_with:base_fee_override,percentage_rate_override,fee_payer_override', 'string', 'max:1000'],
        ];
    }
}
