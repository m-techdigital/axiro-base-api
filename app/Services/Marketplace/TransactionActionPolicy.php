<?php

namespace App\Services\Marketplace;

use App\Models\Transaction;

class TransactionActionPolicy
{
    public function __construct(private TransactionEscrowHandoverService $escrowHandover) {}

    public function allowedCustomerActions(Transaction $transaction, int $customerId): array
    {
        $buyer = $transaction->buyer_customer_id === $customerId;
        $seller = $transaction->seller_customer_id === $customerId;
        $actions = [];

        if ($seller
            && in_array($transaction->status, ['paid', 'partially_paid'], true)
            && $this->startObligationsSatisfied($transaction)
            && $this->escrowHandover->handoverBlockingReason($transaction) === null) {
            $actions[] = 'seller_handover';
        }
        if ($buyer && $transaction->status === 'handover_pending') {
            $actions[] = 'buyer_receive';
        }
        if ($buyer && $transaction->transaction_type === 'rental' && in_array($transaction->status, ['active', 'overdue'], true)) {
            $actions[] = 'renter_return';
        }
        if ($seller && $transaction->transaction_type === 'rental' && $transaction->status === 'return_pending') {
            $actions[] = 'lessor_receive_return';
        }
        if ($buyer
            && bccomp((string) $transaction->paid_amount, (string) $transaction->total_payable, 2) >= 0
            && (($transaction->transaction_type === 'purchase' && $transaction->status === 'handed_over')
                || ($transaction->transaction_type === 'rental' && $transaction->status === 'returned'))) {
            $actions[] = 'complete';
        }
        if (($buyer || $seller)
            && ! in_array($transaction->status, ['agreement_pending', 'completed', 'cancelled'], true)
            && ! $transaction->disputes()->where('status', 'open')->exists()) {
            $actions[] = 'open_dispute';
        }

        return $actions;
    }

    public function allowedAdminActions(Transaction $transaction): array
    {
        $actions = [];

        if (in_array($transaction->status, ['paid', 'partially_paid', 'handover_pending'], true)) {
            $actions[] = 'force_handover';
        }
        if ($transaction->transaction_type === 'rental'
            && in_array($transaction->status, ['active', 'return_pending', 'overdue'], true)) {
            $actions[] = 'force_return';
        }
        if (in_array($transaction->status, ['handed_over', 'returned'], true)) {
            $actions[] = 'complete';
        }
        if (! in_array($transaction->status, ['completed', 'cancelled'], true)) {
            $actions[] = 'cancel';
        }
        if ($transaction->status === 'cancelled') {
            $actions[] = 'reopen';
        }

        return $actions;
    }

    private function startObligationsSatisfied(Transaction $transaction): bool
    {
        return ! $transaction->payments()
            ->whereIn('status', ['pending', 'rejected', 'overdue'])
            ->where(function ($query) {
                $query->whereNull('due_date')->orWhereDate('due_date', '<=', today());
            })
            ->exists();
    }
}
