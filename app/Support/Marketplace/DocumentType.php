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

    public static function canonical(string $type): string
    {
        return self::LEGACY_ALIASES[$type] ?? $type;
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
