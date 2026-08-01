<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceFeePolicy;

final class MarketplaceFeeCalculator
{
    public function calculate(string $transactionType, string $value): array
    {
        $now = now();
        $policy = MarketplaceFeePolicy::query()->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('transaction_type')->orWhere('transaction_type', $transactionType))
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $now))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $now))
            ->orderByRaw('CASE WHEN transaction_type = ? THEN 0 ELSE 1 END', [$transactionType])->orderBy('priority')->first();
        $buyerRate = (string) ($policy?->buyer_fee_rate ?? 0);
        $sellerRate = (string) ($policy?->seller_fee_rate ?? 0);
        $taxRate = (string) ($policy?->tax_rate ?? 0);
        $buyer = bcadd(bcdiv(bcmul($value, $buyerRate, 6), '100', 2), (string) ($policy?->buyer_fixed_fee ?? 0), 2);
        $seller = bcadd(bcdiv(bcmul($value, $sellerRate, 6), '100', 2), (string) ($policy?->seller_fixed_fee ?? 0), 2);
        $tax = bcdiv(bcmul(bcadd($buyer, $seller, 2), $taxRate, 6), '100', 2);
        $platform = bcadd($buyer, $seller, 2);
        $sellerNet = bcsub($value, $seller, 2);
        if (bccomp($sellerNet, '0.00', 2) < 0) {
            $sellerNet = '0.00';
        }

        return ['buyer_fee_amount' => $buyer, 'seller_fee_amount' => $seller, 'service_fee' => $platform, 'tax_amount' => $tax, 'seller_net_amount' => $sellerNet, 'fee_policy_version' => $policy ? ($policy->code.'@'.$policy->updated_at?->format('YmdHis')) : 'ZERO@1', 'fee_snapshot' => ['policy_id' => $policy?->id, 'policy_code' => $policy?->code, 'buyer_fee_rate' => $buyerRate, 'buyer_fixed_fee' => (string) ($policy?->buyer_fixed_fee ?? 0), 'seller_fee_rate' => $sellerRate, 'seller_fixed_fee' => (string) ($policy?->seller_fixed_fee ?? 0), 'tax_rate' => $taxRate]];
    }
}
