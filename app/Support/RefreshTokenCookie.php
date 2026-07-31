<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Cookie;

class RefreshTokenCookie
{
    public static function make(string $name, string $token, string $configKey = 'auth.refresh_cookie'): Cookie
    {
        $settings = (array) config($configKey, []);

        return cookie(
            $name,
            $token,
            (int) ($settings['ttl_days'] ?? 30) * 1440,
            (string) ($settings['path'] ?? '/'),
            $settings['domain'] ?? null,
            (bool) ($settings['secure'] ?? false),
            true,
            false,
            self::sameSite($settings['same_site'] ?? 'lax'),
        );
    }

    public static function forget(string $name, string $configKey = 'auth.refresh_cookie'): Cookie
    {
        $settings = (array) config($configKey, []);

        return cookie()->forget(
            $name,
            (string) ($settings['path'] ?? '/'),
            $settings['domain'] ?? null,
        );
    }

    private static function sameSite(mixed $value): string
    {
        $sameSite = strtolower((string) $value);

        return in_array($sameSite, ['lax', 'strict', 'none'], true) ? $sameSite : 'lax';
    }
}
