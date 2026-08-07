<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

final class AuditPayloadSanitizer
{
    private const MASK = '[Đã che]';

    /**
     * Exact business/security keys which must never be written to audit payloads.
     * Keys are normalized to snake_case before matching.
     */
    private const SENSITIVE_EXACT_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'authorization',
        'cookie',
        'jwt_secret',
        'otp',
        'verification_code',
        'two_factor_code',
        'card_number',
        'cvv',
        'pin',
        'private_key',
        'recovery_code',
        'phone',
        'phone_number',
        'mobile',
        'mobile_number',
        'cccd',
        'identity_number',
        'id_number',
        'signed_url',
        'provider_url',
        'total_amount',
        'transaction_value',
    ];

    /**
     * Credential suffixes are boundary-matched so safe keys such as
     * token_count and secretary_name are not masked accidentally.
     */
    private const SENSITIVE_SUFFIXES = [
        '_password',
        '_token',
        '_secret',
        '_authorization',
        '_cookie',
        '_private_key',
        '_recovery_code',
    ];

    public static function sanitize(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 8) {
            return '[Đã rút gọn do dữ liệu lồng quá sâu]';
        }

        if ($value instanceof UploadedFile) {
            return [
                'file_name' => $value->getClientOriginalName(),
                'size' => $value->getSize(),
                'mime_type' => $value->getMimeType(),
            ];
        }

        if (is_array($value)) {
            $result = [];

            foreach ($value as $key => $item) {
                $normalizedKey = self::normalizeKey((string) $key);

                if (self::isSensitive($normalizedKey)) {
                    $result[$key] = self::MASK;

                    continue;
                }

                $result[$key] = self::sanitize($item, $depth + 1);
            }

            return $result;
        }

        if (is_object($value)) {
            return self::sanitize((array) $value, $depth + 1);
        }

        if (is_string($value) && mb_strlen($value) > 3000) {
            return mb_substr($value, 0, 3000).'… [đã rút gọn]';
        }

        return $value;
    }

    private static function isSensitive(string $key): bool
    {
        if (in_array($key, self::SENSITIVE_EXACT_KEYS, true)) {
            return true;
        }

        foreach (self::SENSITIVE_SUFFIXES as $suffix) {
            if (str_ends_with($key, $suffix)) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeKey(string $key): string
    {
        $snakeCase = preg_replace('/(?<!^)[A-Z]/', '_$0', trim($key));
        $normalized = strtolower((string) $snakeCase);

        return trim((string) preg_replace('/[^a-z0-9]+/', '_', $normalized), '_');
    }
}
