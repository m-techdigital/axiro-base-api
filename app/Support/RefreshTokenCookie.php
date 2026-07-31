<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Cookie;

class RefreshTokenCookie
{
    public static function make(string $name, string $token): Cookie
    {
        return cookie(
            $name,
            $token,
            (int) config('auth.refresh_cookie.ttl_days', 30) * 1440,
            '/',
            config('auth.refresh_cookie.domain'),
            (bool) config('auth.refresh_cookie.secure', false),
            true,
            false,
            (string) config('auth.refresh_cookie.same_site', 'lax'),
        );
    }

    public static function forget(string $name): Cookie
    {
        return cookie()->forget(
            $name,
            '/',
            config('auth.refresh_cookie.domain'),
        );
    }
}
