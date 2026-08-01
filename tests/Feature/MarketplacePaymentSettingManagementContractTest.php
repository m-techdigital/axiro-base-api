<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MarketplacePaymentSettingManagementContractTest extends TestCase
{
    #[Test]
    public function payment_setting_management_has_validation_history_and_restore_boundaries(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/MarketplacePaymentSettingController.php'));
        $request = file_get_contents(app_path('Http/Requests/MarketplacePaymentSettingRequest.php'));
        $routes = file_get_contents(base_path('routes/api/admin.php'));

        $this->assertStringContainsString('MarketplacePaymentSettingRequest $request', $controller);
        $this->assertStringContainsString('DB::transaction', $controller);
        $this->assertStringContainsString('function history()', $controller);
        $this->assertStringContainsString('function activate(', $controller);
        $this->assertStringContainsString("'transfer_prefix'", $request);
        $this->assertStringContainsString('payment-settings/history', $routes);
        $this->assertStringContainsString('payment-settings/{paymentSetting}/activate', $routes);
    }
}
