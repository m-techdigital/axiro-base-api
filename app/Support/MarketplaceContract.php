<?php

namespace App\Support;

use RuntimeException;

final class MarketplaceContract
{
    private static ?array $contract = null;

    public static function all(): array
    {
        if (self::$contract !== null) {
            return self::$contract;
        }

        $path = resource_path('contracts/marketplace-contract.json');
        $raw = @file_get_contents($path);
        $contract = is_string($raw) ? json_decode($raw, true) : null;

        if (! is_array($contract)) {
            throw new RuntimeException('Hợp đồng tích hợp Marketplace không hợp lệ.');
        }

        foreach (['contract_name', 'contract_version', 'api_version', 'capabilities', 'statuses'] as $requiredKey) {
            if (! array_key_exists($requiredKey, $contract)) {
                throw new RuntimeException("Hợp đồng tích hợp Marketplace thiếu trường {$requiredKey}.");
            }
        }

        return self::$contract = $contract;
    }

    public static function version(): string
    {
        return (string) self::all()['contract_version'];
    }

    public static function apiVersion(): string
    {
        return (string) self::all()['api_version'];
    }

    public static function hash(): string
    {
        return hash('sha256', json_encode(self::all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public static function runtime(): array
    {
        $contract = self::all();

        return [
            'api_version' => self::apiVersion(),
            'contract_version' => self::version(),
            'contract_hash' => self::hash(),
            'marketplace' => (bool) ($contract['capabilities']['marketplace_products'] ?? false),
            'customer_auth' => (bool) ($contract['capabilities']['customer_auth'] ?? false),
            'capabilities' => $contract['capabilities'],
        ];
    }
}
