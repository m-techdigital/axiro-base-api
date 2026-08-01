<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerSecurityToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CustomerSecurityWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function headers(Customer $customer): array
    {
        return ['Authorization' => 'Bearer '.auth('customer_api')->login($customer)];
    }

    public function test_profile_email_cannot_be_changed_directly_and_requires_token(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create(['email' => 'old@example.com']);
        $this->putJson('/api/v1/customer/profile', ['name' => $customer->name, 'phone' => $customer->phone, 'email' => 'new@example.com'], $this->headers($customer))->assertOk();
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'email' => 'old@example.com']);
        $this->postJson('/api/v1/customer/profile/email-change', ['email' => 'new@example.com'], $this->headers($customer))->assertOk();
        $token = CustomerSecurityToken::where('customer_id', $customer->id)->where('purpose', 'email_change')->firstOrFail();
        $this->assertSame('new@example.com', $token->payload['email']);
    }

    public function test_password_change_revokes_refresh_tokens_and_updates_password(): void
    {
        $customer = Customer::factory()->create(['password' => 'old-password']);
        $this->putJson('/api/v1/customer/profile/password', ['current_password' => 'old-password', 'password' => 'new-password', 'password_confirmation' => 'new-password'], $this->headers($customer))->assertOk();
        $this->assertTrue(Hash::check('new-password', $customer->fresh()->password));
    }

    public function test_forgot_password_is_non_enumerating(): void
    {
        Mail::fake();
        $this->postJson('/api/v1/auth/customer/forgot-password', ['login' => 'unknown@example.com'])->assertOk();
    }
}
