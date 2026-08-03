<?php

namespace App\Support\Marketplace;

use App\Models\Transaction;

class TransactionLifecycleCatalog
{
    private const STATUS = [
        'pending_payment' => ['label' => 'Chờ thanh toán', 'color' => 'gold', 'phase' => 'payment'],
        'partially_paid' => ['label' => 'Thanh toán một phần', 'color' => 'orange', 'phase' => 'payment'],
        'paid' => ['label' => 'Đã thanh toán', 'color' => 'blue', 'phase' => 'handover'],
        'handover_pending' => ['label' => 'Chờ xác nhận bàn giao', 'color' => 'cyan', 'phase' => 'handover'],
        'handed_over' => ['label' => 'Đã bàn giao', 'color' => 'geekblue', 'phase' => 'acceptance'],
        'active' => ['label' => 'Đang thuê', 'color' => 'green', 'phase' => 'rental'],
        'return_pending' => ['label' => 'Chờ hoàn trả', 'color' => 'purple', 'phase' => 'return'],
        'returned' => ['label' => 'Đã hoàn trả', 'color' => 'blue', 'phase' => 'settlement'],
        'overdue' => ['label' => 'Quá hạn', 'color' => 'red', 'phase' => 'return'],
        'disputed' => ['label' => 'Đang tranh chấp', 'color' => 'volcano', 'phase' => 'dispute'],
        'completed' => ['label' => 'Hoàn tất', 'color' => 'green', 'phase' => 'done'],
        'cancelled' => ['label' => 'Đã hủy', 'color' => 'default', 'phase' => 'done'],
    ];

    private const ACTION = [
        'confirm_payment' => ['label' => 'Xác nhận thanh toán', 'priority' => 'high'],
        'force_handover' => ['label' => 'Xác nhận bàn giao', 'priority' => 'high'],
        'force_return' => ['label' => 'Xác nhận hoàn trả', 'priority' => 'high'],
        'complete' => ['label' => 'Hoàn tất giao dịch', 'priority' => 'high'],
        'cancel' => ['label' => 'Hủy và quyết toán', 'priority' => 'medium'],
        'reopen' => ['label' => 'Mở lại giao dịch', 'priority' => 'medium'],
        'seller_handover' => ['label' => 'Xác nhận đã bàn giao', 'priority' => 'high'],
        'buyer_receive' => ['label' => 'Xác nhận đã nhận', 'priority' => 'high'],
        'renter_return' => ['label' => 'Gửi yêu cầu hoàn trả', 'priority' => 'high'],
        'lessor_receive_return' => ['label' => 'Xác nhận đã nhận lại', 'priority' => 'high'],
        'open_dispute' => ['label' => 'Mở yêu cầu tranh chấp', 'priority' => 'medium'],
        'pay' => ['label' => 'Thanh toán khoản đến hạn', 'priority' => 'high'],
        'accept_document' => ['label' => 'Xác nhận hồ sơ giao dịch', 'priority' => 'medium'],
    ];

    public static function statusOptions(): array
    {
        return collect(self::STATUS)->map(fn (array $meta, string $value) => ['value' => $value, ...$meta])->values()->all();
    }

    public function describe(Transaction $transaction, string $audience, ?int $customerId = null): array
    {
        $transaction->loadMissing(['payments', 'documents.acceptances', 'disputes', 'checkpoints']);
        $available = $audience === 'admin'
            ? $this->adminAvailable($transaction)
            : $this->customerAvailable($transaction, (int) $customerId);

        $candidate = $audience === 'admin'
            ? ['confirm_payment', 'force_handover', 'force_return', 'complete', 'cancel', 'reopen']
            : ['pay', 'seller_handover', 'buyer_receive', 'renter_return', 'lessor_receive_return', 'complete', 'open_dispute', 'accept_document'];

        $actions = [];
        foreach ($candidate as $key) {
            $reason = $this->blockedReason($transaction, $key, $audience, $customerId);
            $enabled = in_array($key, $available, true) && $reason === null;
            $actions[] = [
                'key' => $key,
                'label' => self::ACTION[$key]['label'] ?? $key,
                'priority' => self::ACTION[$key]['priority'] ?? 'low',
                'enabled' => $enabled,
                'blocked_reason' => $enabled ? null : ($reason ?? 'Không phù hợp với trạng thái hiện tại.'),
            ];
        }

        return [
            'status' => ['value' => $transaction->status, ...(self::STATUS[$transaction->status] ?? ['label' => $transaction->status, 'color' => 'default', 'phase' => 'unknown'])],
            'actions' => $actions,
            'next_action' => collect($actions)->first(fn (array $action) => $action['enabled']) ?? null,
            'blocking_reasons' => collect($actions)->where('enabled', false)->pluck('blocked_reason')->filter()->unique()->values()->all(),
        ];
    }

    private function adminAvailable(Transaction $transaction): array
    {
        $actions = [];
        if ($transaction->payments->contains(fn ($payment) => in_array($payment->status, ['submitted'], true))) {
            $actions[] = 'confirm_payment';
        }
        if (in_array($transaction->status, ['paid', 'partially_paid', 'handover_pending'], true)) {
            $actions[] = 'force_handover';
        }
        if ($transaction->transaction_type === 'rental' && in_array($transaction->status, ['active', 'return_pending', 'overdue'], true)) {
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

    private function customerAvailable(Transaction $transaction, int $customerId): array
    {
        $buyer = (int) $transaction->buyer_customer_id === $customerId;
        $seller = (int) $transaction->seller_customer_id === $customerId;
        $actions = [];
        if ($buyer && $transaction->payments->contains(fn ($payment) => in_array($payment->status, ['pending', 'rejected', 'overdue'], true))) {
            $actions[] = 'pay';
        }
        if ($seller && in_array($transaction->status, ['paid', 'partially_paid'], true)) {
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
        if ($buyer && in_array($transaction->status, ['handed_over', 'returned'], true)) {
            $actions[] = 'complete';
        }
        if (($buyer || $seller) && ! in_array($transaction->status, ['completed', 'cancelled'], true)) {
            $actions[] = 'open_dispute';
        }
        if (($buyer || $seller) && $transaction->documents->contains(fn ($document) => ! $document->acceptances->contains('customer_id', $customerId))) {
            $actions[] = 'accept_document';
        }

        return array_values(array_unique($actions));
    }

    private function blockedReason(Transaction $transaction, string $action, string $audience, ?int $customerId): ?string
    {
        $terminal = in_array($transaction->status, ['completed', 'cancelled'], true);
        $openDispute = $transaction->disputes->contains(fn ($dispute) => ! in_array($dispute->status, ['resolved', 'rejected', 'cancelled'], true));
        $unpaid = $transaction->payments->contains(fn ($payment) => ! in_array($payment->status, ['confirmed', 'refunded'], true));

        return match ($action) {
            'complete' => $openDispute ? 'Phải xử lý tranh chấp trước khi hoàn tất.' : ($unpaid ? 'Chưa thể hoàn tất vì còn khoản thanh toán chưa xác nhận.' : null),
            'force_handover', 'seller_handover' => $unpaid ? 'Chưa thể bàn giao vì thanh toán chưa đủ điều kiện.' : null,
            'force_return', 'renter_return', 'lessor_receive_return' => $transaction->transaction_type !== 'rental' ? 'Chỉ áp dụng cho giao dịch thuê.' : null,
            'cancel' => $terminal ? 'Giao dịch đã kết thúc.' : null,
            'reopen' => $transaction->status !== 'cancelled' ? 'Chỉ giao dịch đã hủy mới được mở lại.' : null,
            'open_dispute' => $terminal ? 'Giao dịch đã kết thúc.' : ($openDispute ? 'Đã có tranh chấp đang mở.' : null),
            'pay' => $audience !== 'customer' || (int) $transaction->buyer_customer_id !== (int) $customerId ? 'Chỉ người mua hoặc người thuê được thanh toán.' : null,
            default => null,
        };
    }
}
