<?php

namespace App\Services\Documents;

use App\Models\Transaction;

class MarketplaceDocumentPayloadBuilder
{
    public function build(Transaction $transaction): array
    {
        $payments = '<table><thead><tr><th>Mã khoản</th><th>Số tiền</th><th>Hạn</th><th>Trạng thái</th></tr></thead><tbody>'.
            $transaction->payments->map(fn ($payment) => '<tr><td>'.e($payment->code).'</td><td class="right">'.$this->money($payment->amount).'</td><td>'.e($payment->due_date ?: '—').'</td><td>'.e($this->label($payment->status)).'</td></tr>')->implode('').'</tbody></table>';

        if ($transaction->payments->isEmpty()) {
            $payments = '<div class="notice">Chưa có kế hoạch thanh toán.</div>';
        }

        $dispute = $transaction->disputes->sortByDesc('id')->first();
        $attributes = collect($transaction->product?->attributes ?: [])
            ->map(fn ($value, $key) => e((string) $key).': '.e(is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)))
            ->implode('<br>') ?: 'Không có thông tin bổ sung.';
        $security = collect($transaction->product?->attributes ?: [])
            ->only(['email_linked', 'phone_linked', 'social_linked', 'two_factor', 'changeable'])
            ->map(fn ($value, $key) => e((string) $key).': '.e(is_bool($value) ? ($value ? 'Có' : 'Không') : (string) $value))
            ->implode('<br>') ?: 'Chưa khai báo chi tiết; các bên phải kiểm tra tại thời điểm bàn giao.';
        $checkpoints = $transaction->checkpoints
            ->sortBy('confirmed_at')
            ->map(fn ($checkpoint) => e($checkpoint->checkpoint).' — '.optional($checkpoint->confirmed_at)->format('d/m/Y H:i').' — '.e($checkpoint->note ?: 'Đã xác nhận'))
            ->implode('<br>') ?: 'Chưa có mốc xác nhận.';

        return [
            'operator_name' => config('marketplace_documents.operator.name'),
            'operator_tax_code' => config('marketplace_documents.operator.tax_code'),
            'operator_address' => config('marketplace_documents.operator.address'),
            'operator_support_phone' => config('marketplace_documents.operator.support_phone'),
            'operator_support_email' => config('marketplace_documents.operator.support_email'),
            'operator_website' => config('marketplace_documents.operator.website'),
            'policy_version' => config('marketplace_documents.policy_version'),
            'document_date' => now()->format('d/m/Y'),
            'document_time' => now()->format('H:i:s'),
            'transaction_code' => $transaction->code,
            'transaction_type' => $transaction->transaction_type === 'rental' ? 'Cho thuê' : 'Bán',
            'purchase_mode' => match ($transaction->purchase_mode) {
                'installment' => 'Trả góp',
                'deposit' => 'Đặt cọc',
                default => 'Thanh toán đủ',
            },
            'product_name' => $transaction->product?->name ?: 'Tài khoản trò chơi',
            'product_code' => $transaction->product?->code ?: '—',
            'product_type' => $transaction->product?->product_type ?: '—',
            'product_offer_modes' => collect($transaction->product?->offer_modes ?: [])->map(fn (string $mode) => match ($mode) {
                'sell' => 'Bán',
                'rent' => 'Cho thuê',
                default => $mode,
            })->implode(', ') ?: '—',
            'game_code' => $transaction->product?->game_code ?: '—',
            'server_name' => $transaction->product?->server_name ?: '—',
            'level' => $transaction->product?->level ?: '—',
            'product_attributes' => $attributes,
            'product_security_state' => $security,
            'buyer_name' => $transaction->buyer?->name ?: '—',
            'buyer_code' => $transaction->buyer?->code ?: '—',
            'buyer_phone' => $transaction->buyer?->phone ?: '—',
            'buyer_email' => $transaction->buyer?->email ?: '—',
            'seller_name' => $transaction->seller?->name ?: '—',
            'seller_code' => $transaction->seller?->code ?: '—',
            'seller_phone' => $transaction->seller?->phone ?: '—',
            'seller_email' => $transaction->seller?->email ?: '—',
            'transaction_value' => $this->money($transaction->transaction_value),
            'service_fee' => $this->money($transaction->service_fee),
            'discount' => $this->money($transaction->discount),
            'deposit_amount' => $this->money($transaction->deposit_amount),
            'initial_payment_amount' => $this->money($transaction->initial_payment_amount),
            'installment_count' => $transaction->installment_count ?: '—',
            'total_payable' => $this->money($transaction->total_payable),
            'paid_amount' => $this->money($transaction->paid_amount),
            'remaining_amount' => $this->money(max(0, (float) $transaction->total_payable - (float) $transaction->paid_amount)),
            'refunded_amount' => $this->money($transaction->refunded_amount),
            'transaction_date' => optional($transaction->transaction_date)->format('d/m/Y') ?: (string) $transaction->transaction_date,
            'due_date' => optional($transaction->due_date)->format('d/m/Y') ?: ($transaction->due_date ?: '—'),
            'rental_start' => optional($transaction->rental_start_at)->format('d/m/Y H:i') ?: ($transaction->rental_start_at ?: '—'),
            'rental_end' => optional($transaction->rental_end_at)->format('d/m/Y H:i') ?: ($transaction->rental_end_at ?: '—'),
            'status' => $this->label($transaction->status),
            'payment_method' => $transaction->payment_method ?: 'Chưa xác định',
            'payment_schedule' => $payments,
            'asset_delivery_method' => $transaction->asset_delivery_method ?: '—',
            'asset_delivery_method_label' => $this->deliveryMethodLabel($transaction->asset_delivery_method),
            'inspection_period_minutes' => $transaction->inspection_period_minutes ?: '—',
            'inspection_deadline_at' => optional($transaction->inspection_deadline_at)->format('d/m/Y H:i') ?: 'Chưa bắt đầu',
            'requires_pre_handover_snapshot' => $transaction->requires_pre_handover_snapshot ? 'Có' : 'Không',
            'seller_delivery_note' => $transaction->seller_delivery_note ?: 'Chưa có ghi chú bàn giao.',
            'buyer_inspection_note' => $transaction->buyer_inspection_note ?: 'Chưa có ghi chú kiểm tra.',
            'handover_time' => optional($transaction->handed_over_at)->format('d/m/Y H:i') ?: 'Chưa xác nhận',
            'return_time' => optional($transaction->returned_at)->format('d/m/Y H:i') ?: 'Chưa xác nhận',
            'completed_at' => optional($transaction->completed_at)->format('d/m/Y H:i') ?: 'Chưa hoàn tất',
            'checkpoint_summary' => $checkpoints,
            'dispute_reason' => $dispute?->reason ?: '—',
            'dispute_description' => $dispute?->description ?: 'Chưa có tranh chấp.',
            'dispute_resolution' => $dispute?->resolution ?: 'Chưa có kết luận.',
            'dispute_resolved_at' => optional($dispute?->resolved_at)->format('d/m/Y H:i') ?: 'Chưa xử lý',
            'refund_reason' => $transaction->note ?: 'Theo kết quả đối soát giao dịch.',
            'note' => $transaction->note ?: 'Không có ghi chú.',
        ];
    }

    private function deliveryMethodLabel(?string $value): string
    {
        return match ($value) {
            'account_credentials' => 'Bàn giao quyền truy cập tài khoản',
            'email_transfer' => 'Chuyển quyền qua thư điện tử liên kết',
            'in_game_trade' => 'Giao dịch trực tiếp trong trò chơi',
            'gift_code' => 'Bàn giao mã quà tặng / mã kích hoạt',
            default => $value ?: 'Chưa xác định',
        };
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 0, ',', '.').' đ';
    }

    private function label(string $value): string
    {
        return match ($value) {
            'pending' => 'Đang chờ',
            'submitted' => 'Đã gửi đối soát',
            'confirmed' => 'Đã xác nhận',
            'rejected' => 'Bị từ chối',
            'pending_payment' => 'Chờ thanh toán',
            'partially_paid' => 'Đã thanh toán một phần',
            'paid' => 'Đã thanh toán',
            'handover_pending' => 'Chờ bên nhận xác nhận',
            'handed_over' => 'Đã bàn giao',
            'active' => 'Đang thuê',
            'return_pending' => 'Chờ xác nhận hoàn trả',
            'returned' => 'Đã hoàn trả',
            'completed' => 'Hoàn tất',
            'cancelled' => 'Đã hủy',
            'disputed' => 'Đang tranh chấp',
            default => $value,
        };
    }
}
