<?php

namespace App\Services\Marketplace;

use App\Models\Transaction;
use App\Services\Marketplace\Operations\MarketplaceOperationsReadService;
use App\Support\Marketplace\TransactionLifecycleCatalog;

class TransactionDetailPresenter
{
    public function __construct(
        private TransactionLifecycleCatalog $catalog,
        private MarketplaceOperationsReadService $operations,
    ) {}

    public function admin(Transaction $transaction): array
    {
        $transaction->loadMissing(['product', 'buyer', 'seller', 'payments', 'documents.acceptances', 'events', 'disputes', 'checkpoints']);

        return [
            'lifecycle' => $this->catalog->describe($transaction, 'admin'),
            'workflow_checklist' => $this->operations->documentChecklist($transaction),
            'pending_payments' => $transaction->payments->whereIn('status', ['submitted', 'pending', 'overdue'])->values(),
            'open_dispute' => $transaction->disputes->first(fn ($dispute) => ! in_array($dispute->status, ['resolved', 'rejected', 'cancelled'], true)),
            'escrow_handover' => [
                'product_type' => $transaction->product?->product_type,
                'delivery_method' => $transaction->asset_delivery_method,
                'inspection_period_minutes' => $transaction->inspection_period_minutes,
                'inspection_deadline_at' => $transaction->inspection_deadline_at,
                'requires_pre_handover_snapshot' => (bool) $transaction->requires_pre_handover_snapshot,
                'seller_delivery_note' => $transaction->seller_delivery_note,
                'buyer_inspection_note' => $transaction->buyer_inspection_note,
            ],
            'settlement' => [
                'total_payable' => $transaction->total_payable,
                'paid_amount' => $transaction->paid_amount,
                'escrow_amount' => $transaction->escrow_amount,
                'released_amount' => $transaction->released_amount,
                'refunded_amount' => $transaction->refunded_amount,
                'deposit_amount' => $transaction->deposit_amount,
                'rental_deposit_deduction_amount' => $transaction->rental_deposit_deduction_amount,
            ],
        ];
    }

    public function customer(Transaction $transaction, int $customerId): array
    {
        $transaction->loadMissing(['product', 'payments', 'documents.acceptances', 'events', 'disputes', 'checkpoints']);

        return [
            'lifecycle' => $this->catalog->describe($transaction, 'customer', $customerId),
            'workflow_checklist' => $this->operations->documentChecklist($transaction),
            'escrow_handover' => [
                'product_type' => $transaction->product?->product_type,
                'delivery_method' => $transaction->asset_delivery_method,
                'inspection_period_minutes' => $transaction->inspection_period_minutes,
                'inspection_deadline_at' => $transaction->inspection_deadline_at,
                'requires_pre_handover_snapshot' => (bool) $transaction->requires_pre_handover_snapshot,
                'seller_delivery_note' => $transaction->seller_delivery_note,
                'buyer_inspection_note' => $transaction->buyer_inspection_note,
            ],
            'amount_due' => $transaction->payments->whereIn('status', ['pending', 'rejected', 'overdue'])->sum(fn ($payment) => (float) $payment->amount),
            'documents_pending_acceptance' => $transaction->documents->filter(fn ($document) => ! $document->acceptances->contains('customer_id', $customerId))->count(),
        ];
    }
}
