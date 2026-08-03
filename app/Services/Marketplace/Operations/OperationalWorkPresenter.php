<?php

namespace App\Services\Marketplace\Operations;

use App\Models\GeneratedDocument;
use App\Models\MarketplaceNotification;
use App\Models\Transaction;
use App\Models\WithdrawalRequest;
use App\Support\Marketplace\TransactionLifecycleCatalog;

class OperationalWorkPresenter
{
    public function __construct(private TransactionLifecycleCatalog $lifecycle) {}

    public function todayWork(): array
    {
        return [
            'counters' => [
                'pending_payouts' => WithdrawalRequest::query()->whereIn('status', ['submitted', 'approved'])->count(),
                'stuck_transactions' => Transaction::query()->whereIn('status', ['pending_payment', 'paid', 'handover_pending', 'return_pending', 'overdue', 'disputed'])->count(),
                'documents_waiting' => GeneratedDocument::query()->whereHas('acceptances', fn ($q) => $q->whereNull('accepted_at'))->count(),
                'unhandled_notifications' => MarketplaceNotification::query()->whereNull('handled_at')->count(),
            ],
            'queues' => [
                'payouts' => WithdrawalRequest::query()->with('customer:id,code,name')->whereIn('status', ['submitted', 'approved'])->oldest('submitted_at')->limit(10)->get(),
                'transactions' => Transaction::query()->with(['buyer:id,code,name', 'product:id,code,name'])->whereIn('status', ['pending_payment', 'paid', 'handover_pending', 'return_pending', 'overdue', 'disputed'])->oldest('updated_at')->limit(10)->get()->map(function (Transaction $transaction) {
                    $transaction->setAttribute('lifecycle', $this->lifecycle->describe($transaction, 'admin'));

                    return $transaction;
                }),
                'notifications' => MarketplaceNotification::query()->with(['customer:id,code,name', 'transaction:id,code,status'])->whereNull('handled_at')->latest('id')->limit(10)->get(),
            ],
        ];
    }

    public function documentChecklist(Transaction $transaction): array
    {
        $transaction->loadMissing(['payments', 'checkpoints', 'disputes']);

        $confirmedPayments = $transaction->payments->where('status', 'confirmed')->count();
        $handoverDone = $transaction->checkpoints->contains('checkpoint', 'seller_handover')
            || in_array($transaction->status, ['handover_pending', 'handed_over', 'active', 'return_pending', 'returned', 'completed'], true);
        $acceptanceDone = $transaction->checkpoints->contains('checkpoint', 'buyer_received')
            || in_array($transaction->status, ['handed_over', 'active', 'return_pending', 'returned', 'completed'], true);
        $openDispute = $transaction->disputes->first(
            fn ($dispute): bool => ! in_array($dispute->status, ['resolved', 'rejected', 'cancelled'], true),
        );
        $hasResolvedDispute = $transaction->disputes->contains(
            fn ($dispute): bool => in_array($dispute->status, ['resolved', 'rejected', 'cancelled'], true),
        );

        return [
            [
                'key' => 'payment',
                'label' => 'Thanh toán',
                'status' => $confirmedPayments > 0 ? 'completed' : 'pending',
                'detail' => $confirmedPayments > 0
                    ? $confirmedPayments.' khoản đã xác nhận'
                    : 'Chưa có khoản thanh toán được xác nhận',
            ],
            [
                'key' => 'handover',
                'label' => 'Bàn giao',
                'status' => $handoverDone ? 'completed' : 'pending',
                'detail' => $handoverDone ? 'Đã ghi nhận bàn giao' : 'Chưa ghi nhận bàn giao',
            ],
            [
                'key' => 'acceptance',
                'label' => 'Xác nhận nhận/hoàn trả',
                'status' => $acceptanceDone ? 'completed' : 'pending',
                'detail' => $acceptanceDone ? 'Đã có xác nhận của bên nhận' : 'Đang chờ xác nhận',
            ],
            [
                'key' => 'dispute',
                'label' => 'Tranh chấp',
                'status' => $openDispute ? 'attention' : ($hasResolvedDispute ? 'completed' : 'not_required'),
                'detail' => $openDispute
                    ? 'Có tranh chấp đang mở'
                    : ($hasResolvedDispute ? 'Tranh chấp đã được xử lý' : 'Không có tranh chấp'),
            ],
        ];
    }

    public function slaSummary(): array
    {
        $definitions = [
            'pending_payment' => ['statuses' => ['pending_payment'], 'minutes' => 30],
            'delivery' => ['statuses' => ['paid', 'handover_pending'], 'minutes' => 120],
            'acceptance' => ['statuses' => ['handed_over', 'acceptance_pending', 'return_pending'], 'minutes' => 1440],
            'dispute' => ['statuses' => ['disputed'], 'minutes' => 240],
        ];

        return collect($definitions)->mapWithKeys(function (array $definition, string $key): array {
            $query = Transaction::query()->whereIn('status', $definition['statuses']);
            $total = (clone $query)->count();
            $breached = (clone $query)->where('updated_at', '<=', now()->subMinutes($definition['minutes']))->count();

            return [$key => [
                'total' => $total,
                'breached' => $breached,
                'within_sla' => max(0, $total - $breached),
                'target_minutes' => $definition['minutes'],
            ]];
        })->all();
    }
}
