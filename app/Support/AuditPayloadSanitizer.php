<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

final class AuditPayloadSanitizer
{
    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'current_password', 'new_password',
        'token', 'access_token', 'refresh_token', 'authorization', 'cookie',
        'secret', 'jwt_secret', 'otp', 'verification_code', 'two_factor_code',
        'card_number', 'cvv', 'pin', 'private_key', 'recovery_code',
    ];

    public static function sanitize(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 8) {
            return '[Đã rút gọn do dữ liệu lồng quá sâu]';
        }
        if ($value instanceof UploadedFile) {
            return ['file_name' => $value->getClientOriginalName(), 'size' => $value->getSize(), 'mime_type' => $value->getMimeType()];
        }
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $normalized = strtolower((string) $key);
                if (self::isSensitive($normalized)) {
                    $result[$key] = '[Đã che]';

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
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if ($key === $sensitive || str_contains($key, $sensitive)) {
                return true;
            }
        }

        return false;
    }
}
