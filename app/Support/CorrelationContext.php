<?php

namespace App\Support;

use Illuminate\Support\Str;

final class CorrelationContext
{
    /** @var list<string> */
    private static array $stack = [];

    public static function run(?string $correlationId, callable $callback): mixed
    {
        $normalized = self::normalize($correlationId);

        if ($normalized === null) {
            return $callback();
        }

        self::$stack[] = $normalized;

        try {
            return $callback();
        } finally {
            array_pop(self::$stack);
        }
    }

    public static function current(): ?string
    {
        $value = end(self::$stack);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public static function resolve(?string $explicit = null): ?string
    {
        return self::normalize($explicit)
            ?? self::current()
            ?? self::normalize(request()?->header('X-Correlation-ID'));
    }

    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' && Str::isUuid($value)
            ? strtolower($value)
            : null;
    }
}
