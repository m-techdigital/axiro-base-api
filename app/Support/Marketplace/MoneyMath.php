<?php

namespace App\Support\Marketplace;

use InvalidArgumentException;

final class MoneyMath
{
    public const SCALE = 2;

    public static function normalize(int|float|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        $raw = is_float($value)
            ? rtrim(rtrim(sprintf('%.12F', $value), '0'), '.')
            : trim((string) $value);

        if (! preg_match('/^([+-]?)(\d+)(?:\.(\d+))?$/', $raw, $matches)) {
            throw new InvalidArgumentException('Giá trị tiền không hợp lệ.');
        }

        $negative = ($matches[1] ?? '') === '-';
        $whole = ltrim($matches[2], '0') ?: '0';
        $fraction = str_pad($matches[3] ?? '', self::SCALE + 1, '0');
        $kept = substr($fraction, 0, self::SCALE);
        $minor = ((int) $whole * 100) + (int) $kept;

        if ((int) $fraction[self::SCALE] >= 5) {
            $minor++;
        }

        if ($negative) {
            $minor *= -1;
        }

        return self::fromMinor($minor);
    }

    public static function add(int|float|string|null ...$values): string
    {
        $minor = 0;
        foreach ($values as $value) {
            $minor += self::toMinor($value);
        }

        return self::fromMinor($minor);
    }

    public static function subtract(int|float|string|null $left, int|float|string|null $right): string
    {
        return self::fromMinor(self::toMinor($left) - self::toMinor($right));
    }

    public static function multiply(int|float|string|null $value, int|float|string|null $factor): string
    {
        $valueMinor = self::toMinor($value);
        $factorMicros = self::toScaledInteger($factor, 6);

        return self::fromMinor((int) round(($valueMinor * $factorMicros) / 1_000_000));
    }

    public static function max(int|float|string|null $left, int|float|string|null $right): string
    {
        return self::compare($left, $right) >= 0 ? self::normalize($left) : self::normalize($right);
    }

    public static function compare(int|float|string|null $left, int|float|string|null $right): int
    {
        return self::toMinor($left) <=> self::toMinor($right);
    }

    private static function toMinor(int|float|string|null $value): int
    {
        return self::toScaledInteger($value, self::SCALE);
    }

    private static function toScaledInteger(int|float|string|null $value, int $scale): int
    {
        $normalized = $scale === self::SCALE ? self::normalize($value) : self::normalizeFactor($value, $scale);
        $negative = str_starts_with($normalized, '-');
        $digits = str_replace(['-', '.'], '', $normalized);
        $integer = (int) $digits;

        return $negative ? -$integer : $integer;
    }

    private static function normalizeFactor(int|float|string|null $value, int $scale): string
    {
        $raw = is_float($value) ? sprintf('%.12F', $value) : trim((string) ($value ?? 0));
        if (! preg_match('/^([+-]?)(\d+)(?:\.(\d+))?$/', $raw, $matches)) {
            throw new InvalidArgumentException('Giá trị hệ số không hợp lệ.');
        }
        $fraction = str_pad($matches[3] ?? '', $scale, '0');

        return ($matches[1] ?? '').(ltrim($matches[2], '0') ?: '0').'.'.substr($fraction, 0, $scale);
    }

    private static function fromMinor(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';
        $minor = abs($minor);

        return $sign.intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }
}
