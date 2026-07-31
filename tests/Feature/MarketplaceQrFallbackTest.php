<?php

namespace Tests\Feature;

use App\Services\Payments\MarketplaceQrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceQrFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_qr_service_uses_array_fallback_when_no_database_setting_exists(): void
    {
        config()->set('marketplace.bank', null);

        $data = app(MarketplaceQrService::class)->make('NAP-TEST-001', 200000);

        $this->assertSame('MB', $data['bank']['id']);
        $this->assertSame('0123456789', $data['bank']['account_no']);
        $this->assertSame('MBN NAP-TEST-001', $data['transfer_content']);
        $this->assertStringContainsString('amount=200000', $data['qr_url']);
    }

    public function test_qr_service_uses_configured_array_fallback(): void
    {
        config()->set('marketplace.bank', [
            'id' => 'VCB',
            'name' => 'Vietcombank',
            'account_no' => '123456789',
            'account_name' => 'CONG TY MBN',
            'qr_template' => 'compact2',
            'transfer_prefix' => 'TEST',
        ]);

        $data = app(MarketplaceQrService::class)->make('REF-001', 300000);

        $this->assertSame('VCB', $data['bank']['id']);
        $this->assertSame('TEST REF-001', $data['transfer_content']);
        $this->assertStringContainsString('VCB-123456789-compact2.png', $data['qr_url']);
    }
}
