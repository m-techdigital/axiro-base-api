<?php

namespace App\Support\Marketplace;

use App\Models\Transaction;
use App\Services\Marketplace\TransactionEscrowHandoverService;

class TransactionLifecycleCatalog
{
    public function __construct(private TransactionEscrowHandoverService $escrowHandover)
    {
    }

    private const STATUS = [
        'agreement_pending' => ['label' => 'Chờ đối tác chấp nhận', 'color' => 'purple', 'phase' => 'agreement'],
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
        $transaction->loadMissing(['product', 'payments', 'documents.acceptances', 'disputes', 'checkpoints']);
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
            'guidance' => $this->guidance($transaction, $audience, $customerId),
        ];
    }

    private function guidance(Transaction $transaction, string $audience, ?int $customerId): array
    {
        $nextPayment = $transaction->payments
            ->whereIn('status', ['pending', 'rejected', 'overdue'])
            ->sortBy(fn ($payment) => $payment->due_date ?? $payment->period_start ?? $payment->created_at)
            ->first();

        $items = [];
        if ($nextPayment) {
            $items[] = [
                'key' => 'payment',
                'label' => 'Khoản cần thanh toán',
                'value' => (string) $nextPayment->amount,
                'due_at' => optional($nextPayment->due_date)->toDateString(),
                'status' => $nextPayment->status,
                'message' => $nextPayment->status === 'overdue'
                    ? 'Khoản thanh toán đã quá hạn; cần xử lý trước khi giao dịch tiếp tục.'
                    : 'Hoàn tất khoản đến hạn để mở bước bàn giao tiếp theo.',
            ];
        }

        if (in_array($transaction->status, ['paid', 'partially_paid', 'handover_pending'], true)) {
            $items[] = [
                'key' => 'handover',
                'label' => 'Bàn giao',
                'status' => $transaction->status,
                'due_at' => optional($transaction->due_date)->toDateString(),
                'message' => $transaction->status === 'handover_pending'
                    ? 'Bên nhận cần xác nhận đã nhận tài khoản hoặc mở tranh chấp khi thông tin không đúng.'
                    : 'Bên giao cần hoàn tất bàn giao sau khi thanh toán đủ điều kiện.',
            ];
        }

        if ($transaction->asset_delivery_method) {
            $handoverReason = $this->escrowHandover->handoverBlockingReason($transaction);
            $items[] = [
                'key' => 'digital_asset_escrow',
                'label' => 'Bàn giao trung gian tài sản số',
                'status' => $handoverReason ? 'blocked' : $transaction->status,
                'due_at' => optional($transaction->inspection_deadline_at)->toIso8601String(),
                'message' => $handoverReason
                    ?: ($transaction->inspection_deadline_at
                        ? 'Bên nhận đang trong thời gian kiểm tra tài sản trước khi xác nhận.'
                        : 'Tiền được giữ theo settlement lifecycle cho đến khi bàn giao và xác nhận hoàn tất.'),
            ];
        }

        if ($transaction->transaction_type === 'rental') {
            $deduction = (string) ($transaction->rental_deposit_deduction_amount ?? '0.00');
            $deposit = (string) ($transaction->deposit_amount ?? '0.00');
            $refundable = MoneyMath::max('0.00', MoneyMath::subtract($deposit, $deduction));
            $items[] = [
                'key' => 'rental_settlement',
                'label' => 'Đối soát tiền thuê và cọc',
                'status' => $transaction->status,
                'due_at' => optional($transaction->rental_end_at)->toIso8601String(),
                'rental_amount' => (string) ($transaction->transaction_value ?? '0.00'),
                'deposit_amount' => $deposit,
                'deduction_amount' => $deduction,
                'refundable_amount' => $refundable,
                'message' => in_array($transaction->status, ['returned', 'completed'], true)
                    ? 'Đối chiếu khấu trừ và số cọc hoàn lại theo chứng từ đã ghi nhận.'
                    : 'Theo dõi hạn thuê, hoàn trả và chứng từ trước khi quyết toán tiền cọc.',
            ];
        }

        if ($transaction->status === 'disputed') {
            $items[] = [
                'key' => 'dispute',
                'label' => 'Tranh chấp đang chặn giao dịch',
                'status' => 'attention',
                'message' => $audience === 'admin'
                    ? 'Thu thập bằng chứng hai bên và ra quyết định trước khi giải ngân, hoàn tiền hoặc đóng giao dịch.'
                    : 'Theo dõi yêu cầu bằng chứng và quyết định của quản trị viên; tiền đang được giữ cho đến khi xử lý xong.',
            ];
        }

        return $items;
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
        if ($seller
            && in_array($transaction->status, ['paid', 'partially_paid'], true)
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
        $overduePayment = $transaction->payments->first(fn ($payment) => $payment->status === 'overdue');
        $pendingDocument = $transaction->documents->first(
            fn ($document) => $audience === 'customer'
                && ! $document->acceptances->contains('customer_id', (int) $customerId),
        );

        return match ($action) {
            'complete' => $openDispute
                ? 'Phải xử lý tranh chấp trước khi hoàn tất.'
                : ($overduePayment
                    ? 'Chưa thể hoàn tất vì có khoản thanh toán quá hạn: '.($overduePayment->code ?? '#'.$overduePayment->id).'.'
                    : ($unpaid
                        ? 'Chưa thể hoàn tất vì còn khoản thanh toán chưa xác nhận.'
                        : ($pendingDocument
                            ? 'Chưa thể hoàn tất vì còn tài liệu chưa được xác nhận: '.($pendingDocument->code ?? '#'.$pendingDocument->id).'.'
                            : null))),
            'force_handover' => $unpaid ? 'Chưa thể bàn giao vì thanh toán chưa đủ điều kiện.' : null,
            'seller_handover' => $unpaid
                ? 'Chưa thể bàn giao vì thanh toán chưa đủ điều kiện.'
                : $this->escrowHandover->handoverBlockingReason($transaction),
            'force_return', 'renter_return', 'lessor_receive_return' => $transaction->transaction_type !== 'rental' ? 'Chỉ áp dụng cho giao dịch thuê.' : null,
            'cancel' => $terminal ? 'Giao dịch đã kết thúc.' : null,
            'reopen' => $transaction->status !== 'cancelled' ? 'Chỉ giao dịch đã hủy mới được mở lại.' : null,
            'open_dispute' => $terminal ? 'Giao dịch đã kết thúc.' : ($openDispute ? 'Đã có tranh chấp đang mở.' : null),
            'pay' => $audience !== 'customer' || (int) $transaction->buyer_customer_id !== (int) $customerId
                ? 'Chỉ người mua hoặc người thuê được thanh toán.'
                : ($overduePayment
                    ? 'Khoản thanh toán '.($overduePayment->code ?? '#'.$overduePayment->id).' đã quá hạn; vui lòng thanh toán hoặc liên hệ hỗ trợ.'
                    : null),
            'accept_document' => $audience !== 'customer'
                ? 'Chỉ khách hàng liên quan được xác nhận tài liệu.'
                : ($pendingDocument
                    ? null
                    : 'Không còn tài liệu nào đang chờ bạn xác nhận.'),
            default => null,
        };
    }
}
