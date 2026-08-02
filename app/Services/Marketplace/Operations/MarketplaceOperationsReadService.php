<?php

namespace App\Services\Marketplace\Operations;

use App\Models\AuditLog;
use App\Models\CustomerWallet;
use App\Models\GeneratedDocument;
use App\Models\MarketplaceDispute;
use App\Models\Product;
use App\Models\ProductHold;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MarketplaceOperationsReadService
{
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
            default => $query->whereIn('status', ['pending_payment', 'paid', 'handover_pending', 'handed_over', 'acceptance_pending', 'return_pending', 'disputed', 'overdue']),
        };

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $threshold = max(1, min(720, (int) ($filters['age_minutes'] ?? 30)));
        $query->where('updated_at', '<=', now()->subMinutes($threshold));

        return $query->latest('updated_at')->paginate($perPage);
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

    public function documentChecklist(Transaction $transaction): array
    {
        $required = match ($transaction->transaction_type) {
            'rental' => ['rental_contract', 'handover_record', 'return_record'],
            default => $transaction->purchase_mode === 'installment'
                ? ['sale_contract', 'installment_schedule', 'handover_record']
                : ['sale_contract', 'handover_record'],
        };

        $documents = GeneratedDocument::query()->withCount('acceptances')->where('transaction_id', $transaction->id)->get()->keyBy('document_type');

        return collect($required)->map(function (string $type) use ($documents): array {
            $document = $documents->get($type);

            return [
                'document_type' => $type,
                'required' => true,
                'generated' => $document !== null,
                'status' => $document?->status,
                'accepted' => ((int) ($document?->acceptances_count ?? 0)) > 0,
                'document_id' => $document?->id,
            ];
        })->values()->all();
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
