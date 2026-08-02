<?php

namespace App\Support\Marketplace;

final class DocumentType
{
    public const SALE_RECORD = 'sale_record';

    public const RENTAL_RECORD = 'rental_record';

    private const LEGACY_ALIASES = [
        'sale_contract' => self::SALE_RECORD,
        'rental_contract' => self::RENTAL_RECORD,
    ];

    private const LABELS = [
        self::SALE_RECORD => 'Hồ sơ mua bán tài khoản trò chơi',
        self::RENTAL_RECORD => 'Hồ sơ thuê tài khoản trò chơi',
        'installment_appendix' => 'Phụ lục lịch thanh toán trả góp',
        'deposit_confirmation' => 'Thỏa thuận đặt cọc giữ tài khoản',
        'payment_confirmation' => 'Xác nhận thanh toán giao dịch',
        'handover_minutes' => 'Biên bản bàn giao tài khoản',
        'return_minutes' => 'Biên bản hoàn trả tài khoản thuê',
        'dispute_minutes' => 'Biên bản tiếp nhận tranh chấp',
        'dispute_resolution' => 'Biên bản xử lý tranh chấp',
        'refund_settlement' => 'Biên bản hoàn tiền và đối soát',
        'completion_minutes' => 'Biên bản hoàn tất giao dịch',
        'security_checklist' => 'Phiếu kiểm tra bảo mật khi bàn giao',
        'platform_transaction_record' => 'Phiếu ghi nhận giao dịch trên nền tảng',
    ];

    public static function canonical(string $type): string
    {
        return self::LEGACY_ALIASES[$type] ?? $type;
    }

    public static function values(): array
    {
        return array_keys(self::LABELS);
    }

    public static function options(): array
    {
        return collect(self::LABELS)
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    public static function label(string $type): string
    {
        $canonical = self::canonical($type);

        return self::LABELS[$canonical] ?? $canonical;
    }

    public static function aliasesFor(string $type): array
    {
        $canonical = self::canonical($type);
        $aliases = [$canonical];

        foreach (self::LEGACY_ALIASES as $legacy => $target) {
            if ($target === $canonical) {
                $aliases[] = $legacy;
            }
        }

        return array_values(array_unique($aliases));
    }
}
