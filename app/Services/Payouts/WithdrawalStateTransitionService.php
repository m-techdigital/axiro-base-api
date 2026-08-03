<?php

namespace App\Services\Payouts;

use App\Models\WithdrawalRequest;
use App\Services\AuditTrailService;
use App\Services\Wallet\WalletLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WithdrawalStateTransitionService
{
    public function __construct(private WalletLedgerService $ledger) {}

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

            $this->audit(
                $item,
                'withdrawal_cancelled_by_customer',
                'customer',
                $customerId,
                'Tiền tạm giữ đã được hoàn lại số dư khả dụng.',
            );

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

            $item->update([
                'status' => 'approved',
                'approved_at' => now(),
                'reviewed_by' => $adminId,
                'review_note' => null,
            ]);
            $this->audit($item, 'withdrawal_approved', 'admin', $adminId, 'Đã duyệt yêu cầu rút tiền');

            return $item->fresh(['customer', 'payoutAccount']);
        });
    }

    public function reject(WithdrawalRequest $withdrawal, int $adminId, string $note): WithdrawalRequest
    {
        return DB::transaction(function () use ($withdrawal, $adminId, $note) {
            $item = WithdrawalRequest::query()->lockForUpdate()->findOrFail($withdrawal->id);
            if ($item->status === 'rejected') {
                return $item;
            }
            if (! in_array($item->status, ['submitted', 'approved'], true)) {
                throw ValidationException::withMessages(['status' => 'Yêu cầu không thể từ chối ở trạng thái hiện tại.']);
            }

            $this->ledger->restoreHeldToAvailable(
                $item->customer_id,
                (string) $item->amount,
                'withdrawal_released',
                [
                    'idempotency_key' => 'withdrawal-reject:'.$item->id,
                    'reference_type' => 'withdrawal_request',
                    'reference_id' => $item->id,
                ],
            );

            $item->update([
                'status' => 'rejected',
                'review_note' => $note,
                'reviewed_by' => $adminId,
            ]);
            $this->audit($item, 'withdrawal_rejected', 'admin', $adminId, $note);

            return $item->fresh(['customer', 'payoutAccount']);
        });
    }

    public function markPaid(
        WithdrawalRequest $withdrawal,
        int $adminId,
        string $reference,
        ?string $proofUrl = null,
    ): WithdrawalRequest {
        return DB::transaction(function () use ($withdrawal, $adminId, $reference, $proofUrl) {
            $item = WithdrawalRequest::query()->lockForUpdate()->findOrFail($withdrawal->id);
            if ($item->status === 'paid') {
                return $item;
            }
            if ($item->status !== 'approved') {
                throw ValidationException::withMessages(['status' => 'Yêu cầu phải được duyệt trước khi xác nhận chi trả.']);
            }

            $this->ledger->debitHeld(
                $item->customer_id,
                (string) $item->amount,
                'withdrawal_paid',
                [
                    'idempotency_key' => 'withdrawal-paid:'.$item->id,
                    'reference_type' => 'withdrawal_request',
                    'reference_id' => $item->id,
                    'external_reference' => $reference,
                    'confirmed_by' => $adminId,
                ],
            );

            $item->update([
                'status' => 'paid',
                'payment_reference' => $reference,
                'proof_url' => $proofUrl,
                'paid_at' => now(),
                'reviewed_by' => $adminId,
            ]);
            $this->audit($item, 'withdrawal_paid', 'admin', $adminId, 'Đã xác nhận chi trả: '.$reference);

            return $item->fresh(['customer', 'payoutAccount']);
        });
    }

    private function audit(
        WithdrawalRequest $withdrawal,
        string $event,
        string $actorType,
        int $actorId,
        string $description,
    ): void {
        app(AuditTrailService::class)->log([
            'event_type' => $event,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'entity_type' => 'withdrawal_request',
            'entity_id' => $withdrawal->id,
            'context_type' => 'withdrawal',
            'context_id' => $withdrawal->id,
            'title' => 'Cập nhật yêu cầu rút tiền '.$withdrawal->code,
            'description' => $description,
            'metadata' => [
                'status' => $withdrawal->status,
                'customer_id' => $withdrawal->customer_id,
            ],
        ]);
    }
}
