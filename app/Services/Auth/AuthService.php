<?php

namespace App\Services\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthService
{
    public function login(array $credentials): ?array
    {
        $guard = auth('api');
        if (! $token = $guard->attempt($credentials)) {
            return null;
        } $user = $guard->user();
        $user->update(['last_login_at' => now(), 'last_login_ip' => request()->ip()]);
        $refresh = $this->makeRefresh($user);

        return ['user' => $user, 'access_token' => $token, 'refresh_token' => $refresh->token];
    }

    public function refresh(string $value): ?array
    {
        return DB::transaction(function () use ($value) {
            $old = RefreshToken::where('token', $value)->whereNull('revoked_at')->where('expires_at', '>', now())->lockForUpdate()->first();
            if (! $old || ! $old->user) {
                return null;
            } $old->update(['revoked_at' => now()]);
            $token = auth('api')->login($old->user);
            $new = $this->makeRefresh($old->user);

            return ['user' => $old->user, 'access_token' => $token, 'refresh_token' => $new->token];
        });
    }

    public function logout(): void
    {
        $token = request()->bearerToken();
        if ($token) {
            try {
                auth('api')->logout();
            } catch (\Throwable) {
            }
        } RefreshToken::where('user_id', user_id())->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    private function makeRefresh(User $user): RefreshToken
    {
        return RefreshToken::create(['user_id' => $user->id, 'token' => hash('sha256', Str::random(80)), 'expires_at' => now()->addDays((int) config('auth.refresh_cookie.ttl_days', 30))]);
    }
}
