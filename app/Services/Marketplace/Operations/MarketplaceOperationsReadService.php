<?php

namespace App\Services\Marketplace\Operations;

use App\Models\AuditLog;
use App\Models\CustomerWallet;
use App\Models\MarketplaceDispute;
use App\Models\MarketplaceNotification;
use App\Models\ProductHold;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Support\Marketplace\TransactionLifecycleCatalog;
use Illuminate\Database\Eloquent\Builder;

class MarketplaceOperationsReadService
{
    public function __construct(private TransactionLifecycleCatalog $lifecycle) {}

    public function overview(): array
    {
        $now = now();
        $soon = $now->copy()->addMinutes(15);

        return [
            'holds' => [
                'active' => ProductHold::query()->where('status', 'active')->count(),
                'expiring_soon' => ProductHold::query()->where('status', 'active')->whereBetween('hold_until', [$now, $soon])->count(),
                'expired_unreleased' => ProductHold::query()->where('status', 'active')->where('hold_until', '<=', $now)->count(),
                'released_today' => ProductHold::query()->where('status', 'released')->whereDate('released_at', $now->toDateString())->count(),
            ],
            'queues' => [
                'pending_payment' => Transaction::query()->where('status', 'pending_payment')->count(),
                'delivery' => Transaction::query()->whereIn('status', ['paid', 'handover_pending'])->count(),
                'acceptance' => Transaction::query()->whereIn('status', ['handed_over', 'acceptance_pending', 'return_pending'])->count(),
                'dispute' => MarketplaceDispute::query()->whereNotIn('status', ['resolved', 'rejected', 'cancelled'])->count(),
                'overdue_rental' => Transaction::query()->where('transaction_type', 'rental')->where('status', 'overdue')->count(),
                'pending_return' => Transaction::query()->where('transaction_type', 'rental')->whereIn('status', ['active', 'return_pending'])->count(),
                'deposit_deduction_review' => Transaction::query()->where('transaction_type', 'rental')->where('status', 'returned')->where('deposit_amount', '>', 0)->count(),
                'pending_payout' => WithdrawalRequest::query()->whereIn('status', ['submitted', 'approved'])->count(),
            ],
            'finance' => [
                'wallet_available' => (string) CustomerWallet::query()->sum('available_balance'),
                'wallet_held' => (string) CustomerWallet::query()->sum('held_balance'),
                'submitted_deposits' => (string) WalletTransaction::query()->where('type', 'deposit_request')->whereIn('status', ['pending', 'submitted'])->sum('amount'),
                'pending_payouts' => (string) WithdrawalRequest::query()->whereIn('status', ['submitted', 'approved'])->sum('amount'),
                'refunded' => (string) Transaction::query()->sum('refunded_amount'),
            ],
            'idempotency' => [
                'transactions_with_key' => Transaction::query()->whereNotNull('idempotency_key')->count(),
                'duplicate_checkout_replays' => AuditLog::query()->where('event_type', 'checkout_idempotent_replay')->count(),
                'idempotency_conflicts' => AuditLog::query()->where('event_type', 'checkout_idempotency_conflict')->count(),
            ],
            'menu_counters' => [
                'expired_holds' => ProductHold::query()
                    ->where('status', 'active')
                    ->where('hold_until', '<=', $now)
                    ->count(),
                'pending_payment_confirmation' => TransactionPayment::query()
                    ->where('status', 'submitted')
                    ->count(),
                'open_disputes' => MarketplaceDispute::query()
                    ->whereNotIn('status', ['resolved', 'rejected', 'cancelled'])
                    ->count(),
                'unread_notifications' => MarketplaceNotification::query()->whereNull('read_at')->count(),
            ],
            'sla' => $this->slaSummary(),
        ];
    }

    public function holds(array $filters, int $perPage = 20)
    {
        $query = ProductHold::query()->with([
            'product:id,code,name,availability_status,availability_version,hold_expires_at',
            'customer:id,code,name',
        ]);

        $state = $filters['state'] ?? null;
        if ($state === 'expiring_soon') {
            $query->where('status', 'active')->whereBetween('hold_until', [now(), now()->addMinutes(15)]);
        } elseif ($state === 'expired') {
            $query->where(function (Builder $builder): void {
                $builder->where('status', 'expired')
                    ->orWhere(fn (Builder $active): Builder => $active->where('status', 'active')->where('hold_until', '<=', now()));
            });
        } elseif ($state === 'released') {
            $query->where('status', 'released');
        } elseif ($state === 'active') {
            $query->where('status', 'active')->where('hold_until', '>', now());
        } elseif (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }
        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        return $query->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('hold_until')
            ->paginate($perPage);
    }

    public function stuckTransactions(array $filters, int $perPage = 20)
    {
        $queue = $filters['queue'] ?? null;
        $query = Transaction::query()->with([
            'product:id,code,name,availability_status,availability_version',
            'buyer:id,code,name',
            'seller:id,code,name',
            'payments:id,transaction_id,status,due_date,amount',
            'documents:id,transaction_id,document_type,status',
        ]);

        match ($queue) {
            'pending_payment' => $query->where('status', 'pending_payment'),
            'delivery' => $query->whereIn('status', ['paid', 'handover_pending']),
            'acceptance' => $query->whereIn('status', ['handed_over', 'acceptance_pending', 'return_pending']),
            'dispute' => $query->whereHas('disputes', fn (Builder $q): Builder => $q->whereNotIn('status', ['resolved', 'rejected', 'cancelled'])),
            'overdue_rental' => $query->where('transaction_type', 'rental')->where('status', 'overdue'),
            'pending_return' => $query->where('transaction_type', 'rental')->whereIn('status', ['active', 'return_pending']),
            'deposit_deduction_review' => $query->where('transaction_type', 'rental')->where('status', 'returned')->where('deposit_amount', '>', 0),
            default => $query->whereIn('status', ['pending_payment', 'paid', 'handover_pending', 'handed_over', 'acceptance_pending', 'return_pending', 'disputed', 'overdue']),
        };

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $threshold = max(1, min(720, (int) ($filters['age_minutes'] ?? 30)));
        $query->where('updated_at', '<=', now()->subMinutes($threshold));

        $page = $query->latest('updated_at')->paginate($perPage);
        $page->getCollection()->transform(function (Transaction $transaction): Transaction {
            $transaction->setAttribute('lifecycle', $this->lifecycle->describe($transaction, 'admin'));

            return $transaction;
        });

        return $page;
    }

    public function idempotencyAudit(int $perPage = 20)
    {
        return AuditLog::query()
            ->whereIn('event_type', ['checkout_idempotent_replay', 'checkout_idempotency_conflict'])
            ->latest()
            ->paginate($perPage);
    }

    public function reconciliation(): array
    {
        $payments = TransactionPayment::query();

        return [
            'payments' => [
                'submitted_count' => (clone $payments)->where('status', 'submitted')->count(),
                'submitted_amount' => (string) (clone $payments)->where('status', 'submitted')->sum('amount'),
                'overdue_count' => (clone $payments)->where('status', 'overdue')->count(),
                'confirmed_amount' => (string) (clone $payments)->where('status', 'confirmed')->sum('amount'),
            ],
            'wallet' => [
                'available' => (string) CustomerWallet::query()->sum('available_balance'),
                'held' => (string) CustomerWallet::query()->sum('held_balance'),
                'pending_deposits' => (string) WalletTransaction::query()->where('type', 'deposit_request')->whereIn('status', ['pending', 'submitted'])->sum('amount'),
            ],
            'payouts' => [
                'submitted' => (string) WithdrawalRequest::query()->where('status', 'submitted')->sum('amount'),
                'approved' => (string) WithdrawalRequest::query()->where('status', 'approved')->sum('amount'),
                'paid' => (string) WithdrawalRequest::query()->where('status', 'paid')->sum('amount'),
            ],
            'refunds' => [
                'transactions' => Transaction::query()->where('refunded_amount', '>', 0)->count(),
                'amount' => (string) Transaction::query()->sum('refunded_amount'),
            ],
            'imbalances' => [
                'wallet_negative' => CustomerWallet::query()->where(fn (Builder $q): Builder => $q->where('available_balance', '<', 0)->orWhere('held_balance', '<', 0))->count(),
                'transaction_overpaid' => Transaction::query()->whereColumn('paid_amount', '>', 'total_payable')->count(),
                'release_exceeds_escrow' => Transaction::query()->whereColumn('released_amount', '>', 'escrow_amount')->count(),
            ],
        ];
    }

    public function rentalSettlements(array $filters, int $perPage = 20)
    {
        $query = Transaction::query()->with([
            'product:id,code,name',
            'buyer:id,code,name',
            'seller:id,code,name',
            'disputes:id,transaction_id,status,outcome,resolution,resolved_at',
        ])->where('transaction_type', 'rental')
            ->whereIn('status', ['completed', 'cancelled']);

        $this->applyRentalSettlementFilters($query, $filters);

        return $query->latest('completed_at')->latest('id')->paginate($perPage);
    }

    public function rentalSettlementExportRows(array $filters = [])
    {
        $query = Transaction::query()->with([
            'product:id,code,name',
            'buyer:id,code,name',
            'seller:id,code,name',
            'disputes:id,transaction_id,status,outcome,resolution,resolved_at',
        ])->where('transaction_type', 'rental')
            ->whereIn('status', ['completed', 'cancelled']);

        $this->applyRentalSettlementFilters($query, $filters);

        return $query->latest('completed_at')->latest('id')->get();
    }

    public function chunkRentalSettlementExportRows(array $filters, callable $callback, int $size = 500): void
    {
        $query = Transaction::query()->with([
            'product:id,code,name',
            'buyer:id,code,name',
            'seller:id,code,name',
            'disputes:id,transaction_id,status,outcome,resolution,resolved_at',
        ])->where('transaction_type', 'rental')
            ->whereIn('status', ['completed', 'cancelled']);

        $this->applyRentalSettlementFilters($query, $filters);
        $query->orderBy('id')->chunkById($size, $callback);
    }

    private function applyRentalSettlementFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['transaction_id'])) {
            $query->whereKey($filters['transaction_id']);
        }
        if (! empty($filters['customer_id'])) {
            $customerId = $filters['customer_id'];
            $query->where(fn (Builder $builder): Builder => $builder
                ->where('buyer_customer_id', $customerId)
                ->orWhere('seller_customer_id', $customerId));
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('completed_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('completed_at', '<=', $filters['date_to']);
        }
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

    private function slaSummary(): array
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
