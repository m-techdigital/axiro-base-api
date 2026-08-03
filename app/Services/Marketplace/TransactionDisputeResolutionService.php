<?php

namespace App\Services\Marketplace;

use App\Enums\DisputeOutcome;
use App\Enums\ProductAvailabilityStatus;
use App\Models\MarketplaceDispute;
use App\Models\MarketplaceNotification;
use App\Models\Transaction;
use App\Models\TransactionEvent;
use App\Services\ProductAvailabilityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TransactionDisputeResolutionService
{
    public function __construct(
        private MarketplaceNotificationService $notifications,
        private ProductAvailabilityService $availability,
        private TransactionSettlementService $settlements,
    ) {}

    public function open(Transaction $transaction, int $customerId, array $data): MarketplaceDispute
    {
        abort_unless(in_array($customerId, [$transaction->buyer_customer_id, $transaction->seller_customer_id], true), 403);

        return DB::transaction(function () use ($transaction, $customerId, $data) {
            $locked = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);
            $dispute = MarketplaceDispute::create([
                'code' => 'DSP-'.strtoupper(Str::random(10)),
                'transaction_id' => $locked->id,
                'opened_by_customer_id' => $customerId,
                'reason' => $data['reason'],
                'status' => 'open',
                'description' => $data['description'],
                'evidence' => $data['evidence'] ?? [],
            ]);
            $locked->update(['status' => 'disputed']);
            $this->event($locked, 'dispute_opened', 'customer', $customerId, 'Đã mở yêu cầu tranh chấp', $data['description']);

            return $dispute->fresh(['transaction', 'openedBy:id,code,name']);
        });
    }

    public function resolve(MarketplaceDispute $dispute, int $adminId, array $data): MarketplaceDispute
    {
        return DB::transaction(function () use ($dispute, $adminId, $data) {
            $lockedDispute = MarketplaceDispute::query()->lockForUpdate()->findOrFail($dispute->id);
            $transaction = Transaction::query()->lockForUpdate()->findOrFail($lockedDispute->transaction_id);

            if (in_array($lockedDispute->status, ['resolved', 'rejected', 'cancelled'], true)) {
                return $lockedDispute->fresh(['transaction', 'openedBy:id,code,name']);
            }

            $outcome = DisputeOutcome::from($data['outcome']);
            $next = match ($outcome) {
                DisputeOutcome::COMPLETE => 'completed',
                DisputeOutcome::CANCEL_REFUND, DisputeOutcome::CANCEL_NO_REFUND => 'cancelled',
                DisputeOutcome::REOPEN => $transaction->payments()->where('status', 'confirmed')->exists() ? 'paid' : 'pending_payment',
            };

            if ($data['status'] === 'rejected' && $outcome !== DisputeOutcome::REOPEN) {
                throw ValidationException::withMessages(['outcome' => 'Tranh chấp bị từ chối chỉ được đưa giao dịch trở lại luồng xử lý.']);
            }
            if ($data['status'] === 'resolved' && $outcome === DisputeOutcome::REOPEN) {
                throw ValidationException::withMessages(['outcome' => 'Tranh chấp đã chấp nhận phải kết thúc bằng hoàn tất hoặc hủy giao dịch.']);
            }

            $lockedDispute->update([
                'status' => $data['status'],
                'resolution' => $data['resolution'],
                'outcome' => $outcome->value,
                'resolved_at' => now(),
                'resolved_by' => $adminId,
            ]);
            $transaction->update(['status' => $next, 'completed_at' => $next === 'completed' ? now() : null]);

            if ($outcome === DisputeOutcome::COMPLETE) {
                $this->settlements->settleCompleted($transaction);
                if ($transaction->product) {
                    $this->availability->transition(
                        $transaction->product,
                        $transaction->transaction_type === 'rental' ? ProductAvailabilityStatus::AVAILABLE : ProductAvailabilityStatus::SOLD,
                        $transaction,
                        'Tranh chấp được giải quyết và giao dịch hoàn tất',
                    );
                }
            } elseif ($outcome === DisputeOutcome::CANCEL_REFUND) {
                $this->settlements->refundHeldPayments($transaction, 'dispute_cancel');
                $this->settlements->releaseProductAfterCancellation($transaction);
            } elseif ($outcome === DisputeOutcome::CANCEL_NO_REFUND) {
                $this->settlements->releaseProductAfterCancellation($transaction);
            }

            $this->event($transaction, 'dispute_'.$data['status'], 'user', $adminId, 'Đã xử lý tranh chấp', $data['resolution'], [
                'outcome' => $outcome->value,
                'transaction_status' => $next,
            ]);
            $this->notifyOutcome($transaction, $lockedDispute, $outcome, $data['resolution']);
            $this->resolveNotifications($transaction, $adminId, 'Đã xử lý tranh chấp: '.$outcome->value);

            return $lockedDispute->fresh(['transaction', 'openedBy:id,code,name']);
        });
    }

    private function notifyOutcome(Transaction $transaction, MarketplaceDispute $dispute, DisputeOutcome $outcome, string $resolution): void
    {
        $message = $outcome->label().'. Kết luận: '.$resolution;
        $payload = ['dispute_id' => $dispute->id, 'dispute_code' => $dispute->code, 'outcome' => $outcome->value, 'transaction_status' => $transaction->status];
        foreach (array_unique([$transaction->buyer_customer_id, $transaction->seller_customer_id]) as $customerId) {
            $this->notifications->send($customerId, 'dispute_outcome', 'Tranh chấp đã có kết quả', $message, '/account/purchases/'.$transaction->id, $payload + [
                'transaction_id' => $transaction->id,
                'transaction_code' => $transaction->code,
            ]);
        }
    }

    private function resolveNotifications(Transaction $transaction, int $adminId, string $note): void
    {
        MarketplaceNotification::query()->where('transaction_id', $transaction->id)->whereNull('handled_at')->update([
            'handled_at' => now(), 'handled_by' => $adminId, 'handling_note' => $note, 'read_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function event(Transaction $transaction, string $type, ?string $actorType, ?int $actorId, string $title, ?string $description = null, array $metadata = []): TransactionEvent
    {
        return TransactionEvent::create([
            'transaction_id' => $transaction->id, 'event_type' => $type, 'actor_type' => $actorType, 'actor_id' => $actorId,
            'title' => $title, 'description' => $description, 'metadata' => $metadata,
        ]);
    }
}
