<?php

namespace App\Services\Marketplace;

use App\Models\Transaction;
use Illuminate\Validation\ValidationException;

class TransactionEscrowHandoverService
{
    public function handoverBlockingReason(Transaction $transaction): ?string
    {
        if ($transaction->requires_pre_handover_snapshot
            && ! $transaction->assetSnapshots()->where('stage', 'before_handover')->exists()) {
            return 'Cần lưu biên bản hiện trạng trước khi xác nhận bàn giao.';
        }

        return null;
    }

    public function sellerHandover(Transaction $transaction, ?string $note): array
    {
        $transaction->loadMissing('product');
        $product = $transaction->product;

        if ($reason = $this->handoverBlockingReason($transaction)) {
            throw ValidationException::withMessages(['snapshot' => $reason]);
        }

        if ($transaction->requires_pre_handover_snapshot && trim((string) $note) === '') {
            throw ValidationException::withMessages([
                'note' => 'Vui lòng nhập hướng dẫn bàn giao an toàn, không gửi mật khẩu hoặc mã bí mật trong nội dung này.',
            ]);
        }

        $minutes = max(5, (int) ($transaction->inspection_period_minutes ?: $product?->inspection_period_minutes ?: 30));
        $deliveryMethod = $transaction->asset_delivery_method
            ?: $product?->delivery_method
            ?: ($product?->product_type === 'item' ? 'in_game_trade' : 'account_credentials');
        $deliveryNote = trim((string) $note);

        return [
            'status' => 'handover_pending',
            'asset_delivery_method' => $deliveryMethod,
            'inspection_period_minutes' => $minutes,
            'handed_over_at' => $transaction->handed_over_at ?: now(),
            'inspection_deadline_at' => now()->addMinutes($minutes),
            'seller_delivery_note' => $deliveryNote !== ''
                ? $deliveryNote
                : 'Bàn giao theo phương thức đã cấu hình.',
        ];
    }

    public function buyerReceive(Transaction $transaction, ?string $note): array
    {
        return [
            'status' => $transaction->transaction_type === 'rental' ? 'active' : 'handed_over',
            'handed_over_at' => $transaction->handed_over_at ?: now(),
            'buyer_inspection_note' => trim((string) $note) ?: null,
        ];
    }
}
