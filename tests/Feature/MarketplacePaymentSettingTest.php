<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplacePaymentSettingTest extends TestCase
{
    use RefreshDatabase;

    private function adminHeaders(): array
    {
        $admin = User::factory()->create(['username' => 'admin-payment-setting']);

        return ['Authorization' => 'Bearer '.auth('api')->login($admin)];
    }

    public function test_admin_updates_bank_setting_and_qr_preview_uses_same_source(): void
    {
        $headers = $this->adminHeaders();
        $this->putJson('/api/v1/payment-settings', ['bank_id' => 'VCB', 'bank_name' => 'Vietcombank', 'account_no' => '123456789', 'account_name' => 'CONG TY MBN', 'qr_template' => 'compact2', 'transfer_prefix' => 'MBN'], $headers)->assertOk()->assertJsonPath('data.account_no', '123456789');
        $this->postJson('/api/v1/payment-settings/qr-preview', ['amount' => 200000, 'reference' => 'TEST-001'], $headers)->assertOk()->assertJsonPath('data.transfer_content', 'MBN TEST-001')->assertJsonPath('data.bank.account_no', '123456789');
    }
}
