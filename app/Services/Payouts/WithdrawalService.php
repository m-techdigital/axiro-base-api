<?php

namespace App\Services\Payouts;

use App\Models\CustomerPayoutAccount;
use App\Models\CustomerVerification;
use App\Models\WithdrawalRequest;
use App\Services\AuditTrailService;
use App\Services\Marketplace\MarketplaceRiskService;
use App\Services\Wallet\WalletLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WithdrawalService
{
    public function __construct(private WalletLedgerService $ledger, private MarketplaceRiskService $risk) {}

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
        return DB::transaction(function () use ($withdrawal, $customerId) {
            $item = WithdrawalRequest::query()
                ->whereKey($withdrawal->id)
                ->where('customer_id', $customerId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($item->status === 'cancelled_by_customer') {
                return $item;
            }

            if ($item->status !== 'submitted') {
                throw ValidationException::withMessages([
                    'status' => 'Chỉ có thể hủy yêu cầu rút tiền trước khi được duyệt.',
                ]);
            }

            $this->ledger->restoreHeldToAvailable(
                $item->customer_id,
                (string) $item->amount,
                'withdrawal_cancelled',
                [
                    'idempotency_key' => 'withdrawal-cancel:'.$item->id,
                    'reference_type' => 'withdrawal_request',
                    'reference_id' => $item->id,
                ],
            );

            $item->update([
                'status' => 'cancelled_by_customer',
                'review_note' => 'Khách hàng đã hủy trước khi duyệt.',
            ]);

            app(AuditTrailService::class)->log([
                'event_type' => 'withdrawal_cancelled_by_customer',
                'actor_type' => 'customer',
                'actor_id' => $customerId,
                'entity_type' => 'withdrawal_request',
                'entity_id' => $item->id,
                'context_type' => 'withdrawal',
                'context_id' => $item->id,
                'title' => 'Khách hàng hủy yêu cầu rút tiền '.$item->code,
                'description' => 'Tiền tạm giữ đã được hoàn lại số dư khả dụng.',
                'metadata' => [
                    'status' => $item->status,
                    'customer_id' => $item->customer_id,
                ],
            ]);

            return $item->fresh(['payoutAccount']);
        });
    }

    public function approve(WithdrawalRequest $withdrawal, int $adminId): WithdrawalRequest
    {
        return DB::transaction(function () use ($withdrawal, $adminId) {
            $item = WithdrawalRequest::query()->lockForUpdate()->findOrFail($withdrawal->id);
            if ($item->status === 'approved') {
                return $item;
            }
            if ($item->status !== 'submitted') {
                throw ValidationException::withMessages(['status' => 'Yêu cầu không còn ở trạng thái chờ duyệt.']);
            }
            $item->update(['status' => 'approved', 'approved_at' => now(), 'reviewed_by' => $adminId, 'review_note' => null]);
            $this->audit($item, 'withdrawal_approved', $adminId, 'Đã duyệt yêu cầu rút tiền');

            return $item->fresh(['customer', 'payoutAccount']);
        });
    }

    public function reject(WithdrawalRequest $withdrawal, int $adminId, string $note): WithdrawalRequest
    {
        return DB::transaction(function () use ($withdrawal, $adminId, $note) {
            $item = WithdrawalRequest::lockForUpdate()->findOrFail($withdrawal->id);
            if ($item->status === 'rejected') {
                return $item;
            }
            if (! in_array($item->status, ['submitted', 'approved'], true)) {
                throw ValidationException::withMessages(['status' => 'Yêu cầu không thể từ chối ở trạng thái hiện tại.']);
            }
            $base = 'withdrawal-reject:'.$item->id;
            $this->ledger->restoreHeldToAvailable($item->customer_id, (string) $item->amount, 'withdrawal_released', ['idempotency_key' => $base, 'reference_type' => 'withdrawal_request', 'reference_id' => $item->id]);
            $item->update(['status' => 'rejected', 'review_note' => $note, 'reviewed_by' => $adminId]);
            $this->audit($item, 'withdrawal_rejected', $adminId, $note);

            return $item->fresh(['customer', 'payoutAccount']);
        });
    }

    public function markPaid(WithdrawalRequest $withdrawal, int $adminId, string $reference, ?string $proofUrl = null): WithdrawalRequest
    {
        return DB::transaction(function () use ($withdrawal, $adminId, $reference, $proofUrl) {
            $item = WithdrawalRequest::lockForUpdate()->findOrFail($withdrawal->id);
            if ($item->status === 'paid') {
                return $item;
            }
            if ($item->status !== 'approved') {
                throw ValidationException::withMessages(['status' => 'Yêu cầu phải được duyệt trước khi xác nhận chi trả.']);
            }
            $this->ledger->debitHeld($item->customer_id, (string) $item->amount, 'withdrawal_paid', ['idempotency_key' => 'withdrawal-paid:'.$item->id, 'reference_type' => 'withdrawal_request', 'reference_id' => $item->id, 'external_reference' => $reference, 'confirmed_by' => $adminId]);
            $item->update(['status' => 'paid', 'payment_reference' => $reference, 'proof_url' => $proofUrl, 'paid_at' => now(), 'reviewed_by' => $adminId]);
            $this->audit($item, 'withdrawal_paid', $adminId, 'Đã xác nhận chi trả: '.$reference);

            return $item->fresh(['customer', 'payoutAccount']);
        });
    }

    private function audit(WithdrawalRequest $withdrawal, string $event, int $adminId, string $description): void
    {
        app(AuditTrailService::class)->log([
            'event_type' => $event,
            'actor_type' => 'admin',
            'actor_id' => $adminId,
            'entity_type' => 'withdrawal_request',
            'entity_id' => $withdrawal->id,
            'context_type' => 'withdrawal',
            'context_id' => $withdrawal->id,
            'title' => 'Cập nhật yêu cầu rút tiền '.$withdrawal->code,
            'description' => $description,
            'metadata' => ['status' => $withdrawal->status, 'customer_id' => $withdrawal->customer_id],
        ]);
    }
}
