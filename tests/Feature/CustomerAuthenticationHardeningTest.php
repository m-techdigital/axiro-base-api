<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Models\CustomerRefreshToken;
use App\Services\Auth\CustomerTwoFactorService;
use App\Support\RefreshTokenCookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerAuthenticationHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_two_factor_configuration_cannot_be_overwritten_by_setup_endpoint(): void
    {
        $customer = Customer::create([
            'code' => 'CUS-2FA-HARDEN',
            'username' => 'twofactor-harden',
            'name' => 'Two Factor Harden',
            'email' => 'twofactor-harden@example.test',
            'password' => 'password123',
            'status' => 'active',
            'two_factor_secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => Crypt::encryptString('[]'),
            'two_factor_confirmed_at' => now(),
        ]);
        CustomerWallet::create(['customer_id' => $customer->id]);

        $this->expectException(ValidationException::class);
        app(CustomerTwoFactorService::class)->beginSetup($customer);
    }

    public function test_customer_refresh_token_is_cookie_only_and_not_exposed_in_json(): void
    {
        $customer = Customer::create([
            'code' => 'CUS-COOKIE-ONLY',
            'username' => 'cookie-only',
            'name' => 'Cookie Only',
            'email' => 'cookie-only@example.test',
            'password' => 'password123',
            'status' => 'active',
        ]);
        CustomerWallet::create(['customer_id' => $customer->id]);

        $login = $this->postJson('/api/v1/auth/customer/login', [
            'login' => 'cookie-only',
            'password' => 'password123',
        ]);

        $login->assertOk()
            ->assertJsonMissingPath('data.refresh_token')
            ->assertJsonPath('data.customer.username', 'cookie-only');

        $cookieName = config('auth.customer_refresh_cookie.name');
        $cookie = collect($login->headers->getCookies())
            ->first(fn ($candidate) => $candidate->getName() === $cookieName);
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());

        $plainRefreshToken = $cookie->getValue();
        $storedTokenExists = fn (string $value): bool => CustomerRefreshToken::query()
            ->where('customer_id', $customer->id)
            ->where('token', hash('sha256', $value))
            ->whereNull('revoked_at')
            ->exists();

        if (! $storedTokenExists($plainRefreshToken)) {
            try {
                $plainRefreshToken = Crypt::decryptString($plainRefreshToken);
            } catch (DecryptException) {
                $this->fail('Cookie refresh trả về không khớp token đã lưu và cũng không phải payload mã hóa hợp lệ.');
            }
        }

        $this->assertTrue($storedTokenExists($plainRefreshToken));

        $this->postJson('/api/v1/auth/customer/refresh', [
            'refresh_token' => $plainRefreshToken,
        ])->assertUnauthorized();

        $refresh = $this->call(
            'POST',
            '/api/v1/auth/customer/refresh',
            [],
            [$cookieName => $plainRefreshToken],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            '{}',
        );

        $refresh->assertOk()
            ->assertJsonMissingPath('data.refresh_token')
            ->assertJsonStructure(['data' => ['access_token', 'customer']]);

        $this->assertSame(0, CustomerRefreshToken::where('customer_id', $customer->id)
            ->whereNotNull('revoked_at')
            ->count());
        $this->assertSame(1, CustomerRefreshToken::where('customer_id', $customer->id)
            ->whereNull('revoked_at')
            ->count());

        // The same cookie remains valid across consecutive refreshes, preventing
        // concurrent tabs from invalidating each other's customer session.
        $this->call(
            'POST',
            '/api/v1/auth/customer/refresh',
            [],
            [$cookieName => $plainRefreshToken],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            '{}',
        )->assertOk();
    }

    public function test_refresh_cookie_uses_security_configuration(): void
    {
        config()->set('auth.refresh_cookie.domain', '.example.test');
        config()->set('auth.refresh_cookie.secure', true);
        config()->set('auth.refresh_cookie.same_site', 'strict');
        config()->set('auth.refresh_cookie.ttl_days', 7);

        $cookie = RefreshTokenCookie::make('customer_refresh_token', 'plain-token');

        $this->assertSame('.example.test', $cookie->getDomain());
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('strict', $cookie->getSameSite());
        $this->assertSame('/', $cookie->getPath());
    }
    public function test_customer_session_survives_a_fresh_page_bootstrap_via_cookie_refresh(): void
    {
        $customer = Customer::create([
            'code' => 'CUS-RELOAD',
            'username' => 'reload-customer',
            'name' => 'Reload Customer',
            'email' => 'reload-customer@example.test',
            'password' => 'password123',
            'status' => 'active',
        ]);
        CustomerWallet::create(['customer_id' => $customer->id]);

        $login = $this->postJson('/api/v1/auth/customer/login', [
            'login' => 'reload-customer',
            'password' => 'password123',
        ])->assertOk();

        $cookieName = config('auth.customer_refresh_cookie.name');
        $cookie = collect($login->headers->getCookies())
            ->first(fn ($candidate) => $candidate->getName() === $cookieName);
        $this->assertNotNull($cookie);

        $refreshToken = $cookie->getValue();
        if (! CustomerRefreshToken::where('token', hash('sha256', $refreshToken))->exists()) {
            $refreshToken = Crypt::decryptString($refreshToken);
        }

        // Simulate a completely fresh browser page: no in-memory access token,
        // only the persistent HttpOnly refresh cookie remains.
        $refresh = $this->call(
            'POST',
            '/api/v1/auth/customer/refresh',
            [],
            [$cookieName => $refreshToken],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            '{}',
        )->assertOk();

        $accessToken = $refresh->json('data.access_token');
        $this->assertNotEmpty($accessToken);

        $this->withHeader('Authorization', 'Bearer '.$accessToken)
            ->getJson('/api/v1/auth/customer/me')
            ->assertOk()
            ->assertJsonPath('data.username', 'reload-customer');
    }

    public function test_customer_cookie_has_independent_cross_origin_configuration(): void
    {
        config()->set('auth.customer_refresh_cookie', [
            'name' => 'customer_refresh_token',
            'ttl_days' => 30,
            'path' => '/',
            'domain' => '.example.test',
            'secure' => true,
            'same_site' => 'none',
        ]);

        $cookie = RefreshTokenCookie::make(
            'customer_refresh_token',
            'plain-token',
            'auth.customer_refresh_cookie',
        );

        $this->assertSame('.example.test', $cookie->getDomain());
        $this->assertSame('/', $cookie->getPath());
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('none', $cookie->getSameSite());
    }

}
