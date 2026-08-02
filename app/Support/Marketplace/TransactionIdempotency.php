<?php

namespace App\Support\Marketplace;

final class TransactionIdempotency
{
    public static function hash(int $buyerId, int $productId, array $payload): string
    {
        unset($payload['idempotency_key']);
        self::sortRecursive($payload);

        return hash('sha256', json_encode([
            'buyer_id' => $buyerId,
            'product_id' => $productId,
            'payload' => $payload,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private static function sortRecursive(array &$value): void
    {
        foreach ($value as &$item) {
            if (is_array($item)) {
                self::sortRecursive($item);
            }
        }
        unset($item);
        if (! array_is_list($value)) {
            ksort($value);
        }
    }
}
