<?php

namespace App\Services\Auth;

use App\Models\Customer;
use App\Models\CustomerRefreshToken;
use App\Models\CustomerWallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerAuthService
{
    public function __construct(private CustomerTwoFactorService $twoFactor) {}

    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $customer = Customer::create([
                'code' => 'CUS-'.strtoupper(Str::random(10)),
                'username' => $data['username'],
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'status' => 'active',
            ]);
            CustomerWallet::firstOrCreate(['customer_id' => $customer->id]);

            return $this->issue($customer);
        });
    }

    public function login(array $credentials): ?array
    {
        $guard = $this->guard();
        if (! $token = $guard->attempt($credentials)) {
            return null;
        }

        $customer = $guard->user();
        if ($customer->status !== 'active') {
            $guard->logout();

            return null;
        }

        if ($this->twoFactor->enabled($customer)) {
            try {
                $guard->logout();
            } catch (\Throwable) {
            }

            return [
                'two_factor_required' => true,
                'challenge_token' => $this->twoFactor->issueChallenge($customer),
            ];
        }

        $customer->update(['last_login_at' => now(), 'last_login_ip' => request()->ip()]);

        return $this->issue($customer, $token);
    }

    public function completeTwoFactor(string $challenge, string $code): ?array
    {
        $customer = $this->twoFactor->consumeChallenge($challenge, $code);
        if (! $customer || $customer->status !== 'active') {
            return null;
        }

        $customer->update(['last_login_at' => now(), 'last_login_ip' => request()->ip()]);

        return $this->issue($customer);
    }

    public function refresh(string $value): ?array
    {
        return DB::transaction(function () use ($value) {
            $stored = CustomerRefreshToken::query()
                ->where('token', hash('sha256', $value))
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if (! $stored || ! $stored->customer || $stored->customer->status !== 'active') {
                return null;
            }

            // Sliding one-month customer session. Keeping the opaque refresh token stable
            // avoids concurrent tabs rotating the same cookie and logging each other out.
            $stored->update([
                'last_used_at' => now(),
                'expires_at' => now()->addDays((int) config('auth.customer_refresh_cookie.ttl_days', 30)),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return [
                'customer' => $stored->customer,
                'access_token' => $this->guard()->login($stored->customer),
                'refresh_token' => $value,
            ];
        });
    }

    public function logout(?string $refreshToken = null): void
    {
        $customer = $this->guard()->user();
        try {
            $this->guard()->logout();
        } catch (\Throwable) {
        }

        if (! $customer || ! $refreshToken) {
            return;
        }

        CustomerRefreshToken::query()
            ->where('customer_id', $customer->id)
            ->where('token', hash('sha256', $refreshToken))
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    private function issue(Customer $customer, ?string $access = null): array
    {
        $plain = Str::random(80);
        CustomerRefreshToken::create([
            'customer_id' => $customer->id,
            'token' => hash('sha256', $plain),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'last_used_at' => now(),
            'expires_at' => now()->addDays((int) config('auth.customer_refresh_cookie.ttl_days', 30)),
        ]);

        return [
            'customer' => $customer,
            'access_token' => $access ?: $this->guard()->login($customer),
            'refresh_token' => $plain,
        ];
    }

    private function guard()
    {
        $guard = auth('customer_api');
        $guard->factory()->setTTL((int) config('auth.customer_access_ttl_minutes', 43200));

        return $guard;
    }
}
