<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Services\Auth\AuthService;
use App\Support\RefreshTokenCookie;

class AuthController extends Controller
{
    public function __construct(private AuthService $service) {}

    public function login(LoginRequest $r)
    {
        $data = $this->service->login($r->validated());
        if (! $data) {
            return error_response('Tên đăng nhập hoặc mật khẩu không đúng.', null, 401);
        }

return $this->cookie(success_response(['account' => $data['user']->only(['id', 'username', 'name', 'email']), 'access_token' => $data['access_token'], 'refresh_token' => $data['refresh_token']]), $data['refresh_token']);
    }

    public function refresh(RefreshTokenRequest $r)
    {
        $data = $this->service->refresh((string) $r->refresh_token);
        if (! $data) {
            return error_response('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.', null, 401);
        }

return $this->cookie(success_response(['account' => $data['user']->only(['id', 'username', 'name', 'email']), 'access_token' => $data['access_token'], 'refresh_token' => $data['refresh_token']]), $data['refresh_token']);
    }

    public function me()
    {
        return success_response(user()?->only(['id', 'username', 'name', 'email', 'last_login_at']));
    }

    public function logout()
    {
        $this->service->logout();

        return success_response(null, 'Đã đăng xuất.')->withoutCookie(RefreshTokenCookie::forget(config('auth.refresh_cookie.name')));
    }

    private function cookie($response, string $token)
    {
        return $response->withCookie(RefreshTokenCookie::make(config('auth.refresh_cookie.name'), $token));
    }
}
