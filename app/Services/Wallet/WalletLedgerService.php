<?php

namespace App\Services\Wallet;

use App\Models\CustomerWallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WalletLedgerService
{
    public function wallet(int $customerId, bool $lock = false): CustomerWallet
    {
        $query = CustomerWallet::query()->where('customer_id', $customerId);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first() ?? CustomerWallet::create([
            'customer_id' => $customerId,
            'available_balance' => '0.00',
            'held_balance' => '0.00',
            'lifetime_credit' => '0.00',
            'lifetime_debit' => '0.00',
            'version' => 0,
        ])->refresh();
    }

    public function creditAvailable(int $customerId, string $amount, string $type, array $context = []): WalletTransaction
    {
        return $this->mutate($customerId, $amount, 'credit', 'available', $type, $context);
    }

    public function debitAvailable(int $customerId, string $amount, string $type, array $context = []): WalletTransaction
    {
        return $this->mutate($customerId, $amount, 'debit', 'available', $type, $context);
    }

    public function creditHeld(int $customerId, string $amount, string $type, array $context = []): WalletTransaction
    {
        return $this->mutate($customerId, $amount, 'credit', 'held', $type, $context);
    }

    public function debitHeld(int $customerId, string $amount, string $type, array $context = []): WalletTransaction
    {
        return $this->mutate($customerId, $amount, 'debit', 'held', $type, $context);
    }

    public function reserveAvailable(int $customerId, string $amount, string $type, array $context = []): array
    {
        return DB::transaction(function () use ($customerId, $amount, $type, $context) {
            $base = (string) ($context['idempotency_key'] ?? Str::uuid());
            $out = $this->debitAvailable($customerId, $amount, $type, array_merge($context, ['idempotency_key' => $base.':available', 'internal_transfer' => true]));
            $in = $this->creditHeld($customerId, $amount, $type, array_merge($context, ['idempotency_key' => $base.':held', 'internal_transfer' => true]));

            return [$out, $in];
        });
    }

    public function restoreHeldToAvailable(int $customerId, string $amount, string $type, array $context = []): array
    {
        return DB::transaction(function () use ($customerId, $amount, $type, $context) {
            $base = (string) ($context['idempotency_key'] ?? Str::uuid());
            $out = $this->debitHeld($customerId, $amount, $type, array_merge($context, ['idempotency_key' => $base.':held', 'internal_transfer' => true]));
            $in = $this->creditAvailable($customerId, $amount, $type, array_merge($context, ['idempotency_key' => $base.':available', 'internal_transfer' => true]));

            return [$out, $in];
        });
    }

    public function releaseHeld(int $customerId, string $amount, array $context = []): array
    {
        return DB::transaction(function () use ($customerId, $amount, $context) {
            $base = (string) ($context['idempotency_key'] ?? Str::uuid());
            $out = $this->debitHeld($customerId, $amount, 'escrow_release', array_merge($context, ['idempotency_key' => $base.':held']));
            $in = $this->creditAvailable($customerId, $amount, 'settlement_credit', array_merge($context, ['idempotency_key' => $base.':available']));

            return [$out, $in];
        });
    }

    public function settleHeldWithFee(int $customerId, string $grossAmount, string $sellerNetAmount, array $context = []): array
    {
        return DB::transaction(function () use ($customerId, $grossAmount, $sellerNetAmount, $context) {
            if (bccomp($sellerNetAmount, $grossAmount, 2) > 0) {
                throw ValidationException::withMessages(['settlement' => 'Số tiền ròng không được vượt số tiền tạm giữ.']);
            }
            $base = (string) ($context['idempotency_key'] ?? Str::uuid());
            $out = $this->debitHeld($customerId, $grossAmount, 'settlement_gross_debit', array_merge($context, ['idempotency_key' => $base.':gross', 'internal_transfer' => true]));
            $in = null;
            if (bccomp($sellerNetAmount, '0.00', 2) > 0) {
                $in = $this->creditAvailable($customerId, $sellerNetAmount, 'settlement_net_credit', array_merge($context, ['idempotency_key' => $base.':net', 'internal_transfer' => true]));
            }
            $fee = bcsub($grossAmount, $sellerNetAmount, 2);

            return [$out, $in, $fee];
        });
    }

    public function transferHeldToAvailable(int $fromCustomerId, int $toCustomerId, string $amount, string $type, array $context = []): array
    {
        return DB::transaction(function () use ($fromCustomerId, $toCustomerId, $amount, $type, $context) {
            $base = $context['idempotency_key'] ?? (string) Str::uuid();
            $out = $this->debitHeld($fromCustomerId, $amount, $type.'_debit', array_merge($context, ['idempotency_key' => $base.':debit']));
            $in = $this->creditAvailable($toCustomerId, $amount, $type.'_credit', array_merge($context, ['idempotency_key' => $base.':credit']));

            return [$out, $in];
        });
    }

    public function mutate(int $customerId, string $amount, string $direction, string $bucket, string $type, array $context = []): WalletTransaction
    {
        if (bccomp($amount, '0.00', 2) <= 0) {
            throw ValidationException::withMessages(['amount' => 'Số tiền phải lớn hơn 0.']);
        }

        return DB::transaction(function () use ($customerId, $amount, $direction, $bucket, $type, $context) {
            if (! empty($context['idempotency_key'])) {
                $existing = WalletTransaction::where('idempotency_key', $context['idempotency_key'])->first();
                if ($existing) {
                    return $existing;
                }
            }
            $wallet = $this->wallet($customerId, true);
            $availableBefore = $this->moneyValue($wallet->available_balance);
            $heldBefore = $this->moneyValue($wallet->held_balance);
            $lifetimeCreditBefore = $this->moneyValue($wallet->lifetime_credit);
            $lifetimeDebitBefore = $this->moneyValue($wallet->lifetime_debit);
            $sign = $direction === 'credit' ? 1 : -1;
            if ($bucket === 'available') {
                if ($sign < 0 && bccomp($availableBefore, $amount, 2) < 0) {
                    throw ValidationException::withMessages(['wallet' => 'Số dư khả dụng không đủ.']);
                }
                $availableAfter = $sign > 0 ? bcadd($availableBefore, $amount, 2) : bcsub($availableBefore, $amount, 2);
                $heldAfter = $heldBefore;
            } else {
                if ($sign < 0 && bccomp($heldBefore, $amount, 2) < 0) {
                    throw ValidationException::withMessages(['wallet' => 'Số dư đang tạm giữ không đủ.']);
                }
                $heldAfter = $sign > 0 ? bcadd($heldBefore, $amount, 2) : bcsub($heldBefore, $amount, 2);
                $availableAfter = $availableBefore;
            }
            $wallet->update([
                'available_balance' => $availableAfter, 'held_balance' => $heldAfter,
                'lifetime_credit' => ! empty($context['internal_transfer']) ? $lifetimeCreditBefore : ($direction === 'credit' ? bcadd($lifetimeCreditBefore, $amount, 2) : $lifetimeCreditBefore),
                'lifetime_debit' => ! empty($context['internal_transfer']) ? $lifetimeDebitBefore : ($direction === 'debit' ? bcadd($lifetimeDebitBefore, $amount, 2) : $lifetimeDebitBefore),
                'version' => (int) ($wallet->version ?? 0) + 1,
            ]);

            return WalletTransaction::create([
                'code' => 'WAL-'.strtoupper(Str::random(10)), 'idempotency_key' => $context['idempotency_key'] ?? null,
                'customer_id' => $customerId, 'transaction_id' => $context['transaction_id'] ?? null, 'transaction_payment_id' => $context['transaction_payment_id'] ?? null,
                'type' => $type, 'direction' => $direction, 'balance_bucket' => $bucket, 'amount' => $amount,
                'available_before' => $availableBefore, 'available_after' => $availableAfter, 'held_before' => $heldBefore, 'held_after' => $heldAfter, 'balance_after' => $availableAfter,
                'status' => $context['status'] ?? 'confirmed', 'reference_type' => $context['reference_type'] ?? null, 'reference_id' => $context['reference_id'] ?? null,
                'payment_method' => $context['payment_method'] ?? null, 'external_reference' => $context['external_reference'] ?? null, 'metadata' => $context['metadata'] ?? null,
                'note' => $context['note'] ?? null, 'occurred_at' => now(), 'confirmed_at' => ($context['status'] ?? 'confirmed') === 'confirmed' ? now() : null, 'confirmed_by' => $context['confirmed_by'] ?? null,
            ]);
        });
    }

    private function moneyValue(mixed $value): string
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return '0.00';
        }

        return number_format((float) $value, 2, '.', '');
    }
}
