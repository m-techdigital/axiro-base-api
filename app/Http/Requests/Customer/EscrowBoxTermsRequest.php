<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\HasEscrowBoxValidationAttributes;
use App\Rules\EscrowBoxPublicText;

class EscrowBoxTermsRequest extends ApiFormRequest
{
    use HasEscrowBoxValidationAttributes;

    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:1'],
            'party_a_asset' => ['required', 'array'],
            'party_b_asset' => ['required', 'array'],
            'party_a_asset.type' => ['required', 'in:game_account,item,redeem_code,other'],
            'party_b_asset.type' => ['required', 'in:game_account,item,redeem_code,other'],
            'party_a_asset.title' => ['required', 'string', 'max:180'],
            'party_b_asset.title' => ['required', 'string', 'max:180'],
            'party_a_asset.description' => ['required', 'string', 'max:4000', new EscrowBoxPublicText],
            'party_b_asset.description' => ['required', 'string', 'max:4000', new EscrowBoxPublicText],
            'party_a_asset.reference_value' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'party_b_asset.reference_value' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'party_a_asset.delivery_method' => ['required', 'in:email_transfer,account_credentials,in_game_trade,redeem_code,admin_observed,other'],
            'party_b_asset.delivery_method' => ['required', 'in:email_transfer,account_credentials,in_game_trade,redeem_code,admin_observed,other'],
            'deal_type' => ['required', 'in:exchange,exchange_with_topup'],
            'topup_payer_side' => ['exclude_unless:deal_type,exchange_with_topup', 'required', 'in:party_a,party_b'],
            'topup_amount' => ['exclude_unless:deal_type,exchange_with_topup', 'required', 'numeric', 'min:1000', 'max:999999999999'],
            'fee_payer_mode' => ['required', 'in:party_a,party_b,split_equal'],
            'inspection_period_minutes' => ['required', 'integer', 'min:15', 'max:1440'],
            'success_conditions' => ['required', 'string', 'max:4000', new EscrowBoxPublicText],
            'cancellation_conditions' => ['nullable', 'string', 'max:3000', new EscrowBoxPublicText],
            'additional_terms' => ['nullable', 'string', 'max:3000', new EscrowBoxPublicText],
            'change_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
