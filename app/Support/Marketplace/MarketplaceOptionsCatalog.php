<?php

namespace App\Support\Marketplace;

use App\Enums\DisputeOutcome;

final class MarketplaceOptionsCatalog
{
    public const VERSION = '2026-08-04.1';

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
