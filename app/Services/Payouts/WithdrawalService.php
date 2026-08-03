<?php

namespace App\Services\Payouts;

use App\Models\CustomerPayoutAccount;
use App\Models\CustomerVerification;
use App\Models\WithdrawalRequest;
use App\Services\Marketplace\MarketplaceRiskService;
use App\Services\Wallet\WalletLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WithdrawalService
{
    public function __construct(
        private WalletLedgerService $ledger,
        private MarketplaceRiskService $risk,
        private WithdrawalStateTransitionService $transitions,
    ) {}

    public function submit(int $customerId, int $accountId, string $amount, ?string $note = null, ?string $idempotencyKey = null): WithdrawalRequest
    {
        return DB::transaction(function () use ($customerId, $accountId, $amount, $note, $idempotencyKey) {
            $verification = CustomerVerification::where('customer_id', $customerId)->lockForUpdate()->first();
            if (! $verification || $verification->status !== 'verified') {
                throw ValidationException::withMessages(['verification' => 'Bạn cần hoàn tất xác minh người bán trước khi rút tiền.']);
            }
            $account = CustomerPayoutAccount::whereKey($accountId)->where('customer_id', $customerId)->lockForUpdate()->firstOrFail();
            if ($account->status !== 'verified') {
                throw ValidationException::withMessages(['payout_account_id' => 'Tài khoản nhận tiền chưa được xác minh.']);
            }
            if (bccomp($amount, '50000', 2) < 0) {
                throw ValidationException::withMessages(['amount' => 'Số tiền rút tối thiểu là 50.000 đ.']);
            }
            $key = $idempotencyKey ?: 'withdrawal:'.$customerId.':'.Str::uuid();
            if ($existing = WithdrawalRequest::where('idempotency_key', $key)->first()) {
                return $existing;
            }
            $request = WithdrawalRequest::create(['code' => 'RUT-'.now()->format('ymd').'-'.strtoupper(Str::random(6)), 'idempotency_key' => $key, 'customer_id' => $customerId, 'payout_account_id' => $account->id, 'amount' => $amount, 'fee_amount' => '0.00', 'net_amount' => $amount, 'status' => 'submitted', 'customer_note' => $note, 'submitted_at' => now()]);
            $entries = $this->ledger->reserveAvailable($customerId, $amount, 'withdrawal_reserved', ['idempotency_key' => $key, 'reference_type' => 'withdrawal_request', 'reference_id' => $request->id, 'status' => 'confirmed']);
            $out = $entries[0];
            $request->update(['reserved_wallet_transaction_id' => (string) $out->id]);
            $this->risk->evaluateWithdrawal($request->id, $amount, $customerId);

            return $request->fresh(['payoutAccount']);
        });
    }

    public function cancelByCustomer(WithdrawalRequest $withdrawal, int $customerId): WithdrawalRequest
    {
        return $this->transitions->cancelByCustomer($withdrawal, $customerId);
    }

    public function approve(WithdrawalRequest $withdrawal, int $adminId): WithdrawalRequest
    {
        return $this->transitions->approve($withdrawal, $adminId);
    }

    public function reject(WithdrawalRequest $withdrawal, int $adminId, string $note): WithdrawalRequest
    {
        return $this->transitions->reject($withdrawal, $adminId, $note);
    }

    public function markPaid(WithdrawalRequest $withdrawal, int $adminId, string $reference, ?string $proofUrl = null): WithdrawalRequest
    {
        return $this->transitions->markPaid($withdrawal, $adminId, $reference, $proofUrl);
    }
}
