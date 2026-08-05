<?php

namespace App\Support\Marketplace;

use App\Enums\DisputeOutcome;

final class MarketplaceOptionsCatalog
{
    public const VERSION = '2026-08-05.2';

    public const CACHE_TTL_SECONDS = 300;

    public static function payload(): array
    {
        return [
            'document_types' => DocumentType::options(),
            'document_template_statuses' => [
                ['value' => 'draft', 'label' => 'Bản nháp'],
                ['value' => 'published', 'label' => 'Đã phát hành'],
                ['value' => 'deprecated', 'label' => 'Ngừng sử dụng'],
            ],
            'dispute_outcomes' => DisputeOutcome::options(),
            'transaction_statuses' => TransactionLifecycleCatalog::statusOptions(),
            'game_account_delivery_methods' => [
                ['value' => 'account_credentials', 'label' => 'Thông tin đăng nhập qua kênh bảo mật'],
                ['value' => 'email_transfer', 'label' => 'Chuyển quyền email/liên kết'],
            ],
            'item_delivery_methods' => [
                ['value' => 'in_game_trade', 'label' => 'Giao dịch trực tiếp trong game'],
                ['value' => 'gift_code', 'label' => 'Mã quà tặng / mã vật phẩm'],
            ],
        ];
    }

    public static function hash(): string
    {
        return hash('sha256', json_encode(self::payload(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public static function meta(): array
    {
        return [
            'options_version' => self::VERSION,
            'options_hash' => self::hash(),
            'cache_ttl_seconds' => self::CACHE_TTL_SECONDS,
        ];
    }
}
