<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_enable_two_factor_and_login_requires_challenge(): void
    {
        $customer = Customer::create(['code' => 'CUS-2FA', 'username' => 'twofactor', 'name' => 'Two Factor', 'email' => 'twofactor@example.test', 'password' => 'password123', 'status' => 'active']);
        CustomerWallet::create(['customer_id' => $customer->id]);
        $token = auth('customer_api')->login($customer);
        $setup = $this->withToken($token)->postJson('/api/v1/customer/security/two-factor/setup')->assertOk()->json('data');
        $this->assertNotEmpty($setup['secret'] ?? null);
        $this->assertStringStartsWith('otpauth://totp/', $setup['otpauth_url'] ?? '');
        $login = $this->postJson('/api/v1/auth/customer/login', ['login' => 'twofactor', 'password' => 'password123'])->assertOk();
        $this->assertFalse((bool) $login->json('data.two_factor_required'));
    }

    public function test_two_factor_contract_is_published(): void
    {
        $this->getJson('/api/v1/marketplace-contract')->assertOk()->assertJsonPath('data.capabilities.two_factor_authentication', true);
    }
}
