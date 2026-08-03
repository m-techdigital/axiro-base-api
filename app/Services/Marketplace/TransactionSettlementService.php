<?php

namespace App\Services\Marketplace;

use App\Enums\ProductAvailabilityStatus;
use App\Models\MarketplacePlatformLedgerEntry;
use App\Models\Product;
use App\Models\Transaction;
use App\Services\ProductAvailabilityService;
use App\Services\Wallet\WalletLedgerService;
use Illuminate\Support\Str;

class TransactionSettlementService
{
    public function __construct(
        private WalletLedgerService $wallets,
        private ProductAvailabilityService $availability,
    ) {}

    public function settleCompleted(Transaction $transaction, string $rentalDepositDeduction = '0.00', ?string $deductionNote = null): void
    {
        $payments = $transaction->payments()->where('status', 'confirmed')->where('settlement_status', 'held')->get();
        $gross = '0.00';
        $refunded = '0.00';
        $deducted = '0.00';
        $remainingDeduction = $rentalDepositDeduction;

        foreach ($payments as $payment) {
            if ($transaction->transaction_type === 'rental' && $payment->refundable) {
                $deduction = bccomp($remainingDeduction, (string) $payment->amount, 2) > 0
                    ? (string) $payment->amount
                    : $remainingDeduction;
                $refundAmount = bcsub((string) $payment->amount, $deduction, 2);

                if (bccomp($refundAmount, '0.00', 2) > 0) {
                    $this->wallets->transferHeldToAvailable(
                        $transaction->seller_customer_id,
                        $transaction->buyer_customer_id,
                        $refundAmount,
                        'rental_deposit_refund',
                        $this->paymentContext($transaction, $payment->id, 'deposit-refund'),
                    );
                    $refunded = bcadd($refunded, $refundAmount, 2);
                }

                if (bccomp($deduction, '0.00', 2) > 0) {
                    $context = $this->paymentContext($transaction, $payment->id, 'deposit-deduction');
                    $context['note'] = $deductionNote;
                    $this->wallets->releaseHeld($transaction->seller_customer_id, $deduction, $context);
                    $deducted = bcadd($deducted, $deduction, 2);
                    $remainingDeduction = bcsub($remainingDeduction, $deduction, 2);
                }

                $payment->update([
                    'settlement_status' => bccomp($deduction, '0.00', 2) > 0 ? 'partially_refunded' : 'refunded',
                    'released_at' => now(),
                    'note' => $deductionNote,
                ]);
            } else {
                $gross = bcadd($gross, (string) $payment->amount, 2);
            }
        }

        $released = '0.00';
        if (bccomp($gross, '0.00', 2) > 0) {
            $net = (string) $transaction->seller_net_amount;
            [, , $platformFee] = $this->wallets->settleHeldWithFee(
                $transaction->seller_customer_id,
                $gross,
                $net,
                [
                    'idempotency_key' => 'transaction:'.$transaction->id.':net-settlement',
                    'transaction_id' => $transaction->id,
                    'reference_type' => 'transaction',
                    'reference_id' => $transaction->id,
                    'note' => 'Quyết toán ròng giao dịch '.$transaction->code,
                ],
            );
            MarketplacePlatformLedgerEntry::firstOrCreate(
                ['idempotency_key' => 'transaction:'.$transaction->id.':platform-fee'],
                [
                    'code' => 'PLF-'.strtoupper(Str::random(10)),
                    'transaction_id' => $transaction->id,
                    'type' => 'marketplace_fee',
                    'amount' => $platformFee,
                    'metadata' => [
                        'buyer_fee_amount' => $transaction->buyer_fee_amount,
                        'seller_fee_amount' => $transaction->seller_fee_amount,
                        'tax_amount' => $transaction->tax_amount,
                        'fee_policy_version' => $transaction->fee_policy_version,
                    ],
                    'occurred_at' => now(),
                ],
            );
            $transaction->payments()->where('status', 'confirmed')->where('settlement_status', 'held')->where('refundable', false)
                ->update(['settlement_status' => 'released', 'released_at' => now()]);
            $released = $net;
        }

        $released = bcadd($released, $deducted, 2);
        $transaction->update([
            'released_amount' => bcadd((string) $transaction->released_amount, $released, 2),
            'refunded_amount' => bcadd((string) $transaction->refunded_amount, $refunded, 2),
            'escrow_amount' => '0.00',
        ]);
    }

    public function refundHeldPayments(Transaction $transaction, string $reason): void
    {
        $payments = $transaction->payments()->where('status', 'confirmed')->where('settlement_status', 'held')->get();
        $refunded = '0.00';

        foreach ($payments as $payment) {
            $context = $this->paymentContext($transaction, $payment->id, 'refund:'.$reason);
            $context['note'] = 'Hoàn tiền giao dịch '.$transaction->code;
            $this->wallets->transferHeldToAvailable(
                $transaction->seller_customer_id,
                $transaction->buyer_customer_id,
                (string) $payment->amount,
                'transaction_refund',
                $context,
            );
            $payment->update(['settlement_status' => 'refunded', 'released_at' => now()]);
            $refunded = bcadd($refunded, (string) $payment->amount, 2);
        }

        if (bccomp($refunded, '0.00', 2) > 0) {
            $transaction->update([
                'refunded_amount' => bcadd((string) $transaction->refunded_amount, $refunded, 2),
                'escrow_amount' => '0.00',
            ]);
        }
    }

    public function releaseProductAfterCancellation(Transaction $transaction): void
    {
        $product = Product::lockForUpdate()->find($transaction->product_id);
        if (! $product || $product->availability_status !== 'held') {
            return;
        }

        $anotherPaidTransactionExists = Transaction::query()
            ->where('product_id', $product->id)
            ->where('id', '!=', $transaction->id)
            ->where('status', '!=', 'cancelled')
            ->whereHas('payments', fn ($query) => $query->where('status', 'confirmed'))
            ->exists();

        if (! $anotherPaidTransactionExists) {
            $this->availability->transition($product, ProductAvailabilityStatus::AVAILABLE, $transaction, 'Giao dịch bị hủy');
        }
    }

    private function paymentContext(Transaction $transaction, int $paymentId, string $action): array
    {
        return [
            'idempotency_key' => 'payment:'.$paymentId.':'.$action,
            'transaction_id' => $transaction->id,
            'transaction_payment_id' => $paymentId,
            'reference_type' => 'transaction_payment',
            'reference_id' => $paymentId,
        ];
    }
}
