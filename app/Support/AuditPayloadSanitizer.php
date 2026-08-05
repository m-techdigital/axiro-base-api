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
        'phone', 'phone_number', 'mobile', 'cccd', 'citizen_id', 'identity_number',
        'signed_url', 'provider_url', 'client_secret',
        'total_amount', 'transaction_value',
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
            foreach (array_keys($value) as $index => $key) {
                if ($index >= 50) {
                    $result['__truncated__'] = true;

                    break;
                }

                $item = $value[$key];
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
        if (in_array($key, self::SENSITIVE_KEYS, true)) {
            return true;
        }

        return str_ends_with($key, '_token')
            || str_ends_with($key, '_secret')
            || str_ends_with($key, '_password')
            || str_ends_with($key, '_authorization');
    }
}
