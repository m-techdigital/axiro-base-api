<?php

namespace App\Services\Marketplace;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionEvent;
use App\Models\TransactionPayment;
use App\Services\ProductAvailabilityService;
use App\Services\Wallet\WalletLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionPaymentCaptureService
{
    public function __construct(
        private MarketplaceNotificationService $notifications,
        private WalletLedgerService $wallets,
        private ProductAvailabilityService $availability,
    ) {}

    public function submit(TransactionPayment $payment, int $customerId, array $data): TransactionPayment
    {
        abort_unless($payment->customer_id === $customerId, 403);
        if (! in_array($payment->status, ['pending', 'rejected', 'overdue'], true)) {
            throw ValidationException::withMessages(['payment' => 'Khoản thanh toán không thể gửi lại.']);
        }

        if (($data['payment_method'] ?? null) === 'wallet') {
            return DB::transaction(function () use ($payment, $customerId, $data) {
                $locked = TransactionPayment::lockForUpdate()->findOrFail($payment->id);
                $transaction = Transaction::lockForUpdate()->findOrFail($locked->transaction_id);
                $this->reserveProduct($transaction);
                $walletEntry = $this->wallets->debitAvailable($customerId, (string) $locked->amount, 'transaction_payment', ['idempotency_key' => 'payment:'.$locked->id.':buyer-debit', 'transaction_id' => $transaction->id, 'transaction_payment_id' => $locked->id, 'payment_method' => 'wallet', 'reference_type' => 'transaction_payment', 'reference_id' => $locked->id, 'note' => 'Thanh toán '.$locked->code]);
                $this->wallets->creditHeld($transaction->seller_customer_id, (string) $locked->amount, 'escrow_hold', ['idempotency_key' => 'payment:'.$locked->id.':seller-hold', 'transaction_id' => $transaction->id, 'transaction_payment_id' => $locked->id, 'payment_method' => 'wallet', 'reference_type' => 'transaction_payment', 'reference_id' => $locked->id, 'note' => 'Tạm giữ tiền giao dịch '.$transaction->code]);
                $locked->update(['status' => 'confirmed', 'payment_method' => 'wallet', 'reference' => $data['reference'] ?? $walletEntry->code, 'paid_at' => now(), 'confirmed_at' => now(), 'wallet_transaction_id' => $walletEntry->id, 'settlement_status' => 'held', 'settled_at' => now(), 'note' => $data['note'] ?? null]);
                $this->recalculateState($transaction);
                $this->event($transaction, 'payment_confirmed', 'customer', $customerId, 'Đã thanh toán bằng số dư ví', 'Khoản '.$locked->code.' đã được xác nhận tự động.', ['payment_id' => $locked->id]);

                return $locked->fresh();
            });
        }

        $payment->update(['status' => 'submitted', 'payment_method' => $data['payment_method'], 'reference' => $data['reference'] ?? null, 'paid_at' => now(), 'note' => $data['note'] ?? null]);
        $this->event($payment->transaction, 'payment_submitted', 'customer', $customerId, 'Đã gửi thông tin thanh toán', 'Khách hàng đã gửi thông tin thanh toán '.$payment->code.'.');

        return $payment->fresh();
    }

    public function confirm(TransactionPayment $payment, int $adminId): TransactionPayment
    {
        return DB::transaction(function () use ($payment, $adminId) {
            $locked = TransactionPayment::lockForUpdate()->findOrFail($payment->id);
            if ($locked->status === 'confirmed') {
                return $locked;
            }
            if (! in_array($locked->status, ['pending', 'submitted'], true)) {
                throw ValidationException::withMessages(['payment' => 'Khoản thanh toán không ở trạng thái có thể xác nhận.']);
            }

            $transaction = Transaction::lockForUpdate()->findOrFail($locked->transaction_id);
            $this->reserveProduct($transaction);
            $this->wallets->creditHeld($transaction->seller_customer_id, (string) $locked->amount, 'escrow_hold', ['idempotency_key' => 'payment:'.$locked->id.':seller-hold', 'transaction_id' => $transaction->id, 'transaction_payment_id' => $locked->id, 'payment_method' => $locked->payment_method ?? 'bank', 'reference_type' => 'transaction_payment', 'reference_id' => $locked->id, 'confirmed_by' => $adminId, 'note' => 'Đối soát thanh toán '.$locked->code]);
            $locked->update(['status' => 'confirmed', 'confirmed_at' => now(), 'confirmed_by' => $adminId, 'paid_at' => $locked->paid_at ?? now(), 'settlement_status' => 'held', 'settled_at' => now()]);
            $this->recalculateState($transaction);
            $this->event($transaction, 'payment_confirmed', 'user', $adminId, 'Đã xác nhận thanh toán', 'Khoản '.$locked->code.' đã được xác nhận.', ['payment_id' => $locked->id]);
            foreach ([$transaction->buyer_customer_id, $transaction->seller_customer_id] as $id) {
                $this->notifications->transaction($id, 'payment_confirmed', 'Thanh toán đã được xác nhận', 'Khoản '.$locked->code.' đã được đối soát.', $transaction->id, $transaction->code);
            }

            return $locked->fresh(['transaction']);
        });
    }

    private function reserveProduct(Transaction $transaction): void
    {
        $product = Product::lockForUpdate()->findOrFail($transaction->product_id);
        $conflictingTransactionExists = Transaction::query()
            ->where('product_id', $product->id)
            ->where('id', '!=', $transaction->id)
            ->where('status', '!=', 'cancelled')
            ->whereHas('payments', fn ($query) => $query->where('status', 'confirmed'))
            ->exists();

        if ($conflictingTransactionExists || $product->approval_status !== 'approved' || ! $product->is_published || ! in_array($product->availability_status, ['available', 'held'], true) || ($product->availability_status === 'held' && (int) $product->held_by_transaction_id !== (int) $transaction->id)) {
            throw ValidationException::withMessages(['product' => 'Sản phẩm đã được giữ chỗ bởi một giao dịch khác.']);
        }

        if ($product->availability_status === 'available') {
            $this->availability->hold($product, $transaction->buyer_customer_id, $transaction, 30, 'Giữ chỗ khi xác nhận thanh toán');
        }
    }

    private function recalculateState(Transaction $transaction): void
    {
        $paid = (string) $transaction->payments()->where('status', 'confirmed')->sum('amount');
        $walletPaid = (string) $transaction->payments()->where('status', 'confirmed')->where('payment_method', 'wallet')->sum('amount');
        $paymentStatus = bccomp($paid, (string) $transaction->total_payable, 2) >= 0 ? 'paid' : 'partially_paid';
        $status = in_array($transaction->status, ['pending_payment', 'partially_paid', 'paid', 'overdue'], true) ? $paymentStatus : $transaction->status;
        $next = $transaction->payments()->whereIn('status', ['pending', 'rejected'])->orderBy('due_date')->value('due_date');
        $transaction->update(['paid_amount' => $paid, 'wallet_paid_amount' => $walletPaid, 'escrow_amount' => $paid, 'status' => $status, 'next_payment_due_at' => $next]);
    }

    private function event(Transaction $transaction, string $type, ?string $actorType, ?int $actorId, string $title, ?string $description = null, array $metadata = []): TransactionEvent
    {
        return TransactionEvent::create(['transaction_id' => $transaction->id, 'event_type' => $type, 'actor_type' => $actorType, 'actor_id' => $actorId, 'title' => $title, 'description' => $description, 'metadata' => $metadata]);
    }
}
