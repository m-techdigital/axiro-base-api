<?php

namespace App\Services\Marketplace;

use App\Models\EscrowBox;
use App\Models\EscrowFeeRule;
use App\Support\Marketplace\MoneyMath;

class EscrowBoxFeeService
{
    public function calculate(EscrowBox $box, array $overrides = []): array
    {
        $moneyAmount = MoneyMath::normalize($box->topup_amount);
        $rule = EscrowFeeRule::query()
            ->where('is_active', true)
            ->where('minimum_money_amount', '<=', $moneyAmount)
            ->where(fn ($query) => $query->whereNull('maximum_money_amount')->orWhere('maximum_money_amount', '>=', $moneyAmount))
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', now()))
            ->orderBy('priority')
            ->orderByDesc('id')
            ->first();

        $baseFee = MoneyMath::normalize($overrides['base_fee_override'] ?? $rule?->base_fee ?? '50000');
        $rate = (string) ($overrides['percentage_rate_override'] ?? $rule?->percentage_rate ?? '10');
        $percentageFee = MoneyMath::multiply($moneyAmount, bcdiv($rate, '100', 6));
        $calculated = MoneyMath::add($baseFee, $percentageFee);
        $minimumFee = MoneyMath::normalize($rule?->minimum_fee ?? '50000');
        $final = MoneyMath::max($calculated, $minimumFee);
        if ($rule?->maximum_fee !== null && MoneyMath::compare($final, $rule->maximum_fee) > 0) {
            $final = MoneyMath::normalize($rule->maximum_fee);
        }

        $payerMode = $overrides['fee_payer_override'] ?? $box->fee_payer_mode;
        [$partyAFee, $partyBFee] = match ($payerMode) {
            'party_a' => [$final, '0.00'],
            'split_equal' => [MoneyMath::multiply($final, '0.5'), MoneyMath::subtract($final, MoneyMath::multiply($final, '0.5'))],
            default => ['0.00', $final],
        };

        return [
            'rule' => $rule,
            'base_fee' => $baseFee,
            'percentage_rate' => $rate,
            'percentage_fee' => $percentageFee,
            'money_amount' => $moneyAmount,
            'calculated_fee' => $calculated,
            'final_fee' => $final,
            'party_a_fee_amount' => $partyAFee,
            'party_b_fee_amount' => $partyBFee,
            'fee_payer_mode' => $payerMode,
            'snapshot' => [
                'rule_code' => $rule?->code ?? 'ESCROW-DEFAULT',
                'rule_version' => $rule?->version ?? 1,
                'base_fee' => $baseFee,
                'percentage_rate' => $rate,
                'percentage_fee' => $percentageFee,
                'money_amount' => $moneyAmount,
                'calculated_fee' => $calculated,
                'final_fee' => $final,
                'fee_payer_mode' => $payerMode,
            ],
        ];
    }
}
