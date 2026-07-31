<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Support\MarketplaceContract;
use Tests\TestCase;

class MarketplaceContractCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_runtime_and_contract_publish_compatible_versions(): void
    {
        $this->getJson('/api/v1/runtime')
            ->assertOk()
            ->assertJsonPath('data.api_version', MarketplaceContract::apiVersion())
            ->assertJsonPath('data.contract_version', MarketplaceContract::version())
            ->assertJsonPath('data.contract_hash', MarketplaceContract::hash())
            ->assertJsonPath('meta.contract_version', MarketplaceContract::version())
            ->assertHeader('X-Marketplace-Contract-Version', MarketplaceContract::version())
            ->assertHeader('X-Marketplace-Contract-Hash', MarketplaceContract::hash());

        $this->getJson('/api/v1/marketplace-contract')
            ->assertOk()
            ->assertJsonPath('data.contract_name', 'axiro-mini-marketplace')
            ->assertJsonPath('data.contract_version', MarketplaceContract::version())
            ->assertJsonPath('data.capabilities.card_deposit', false)
            ->assertJsonPath('data.capabilities.audit_logs_admin_only', true);
    }

    public function test_contract_declares_every_customer_and_admin_integration_group(): void
    {
        $contract = json_decode((string) file_get_contents(resource_path('contracts/marketplace-contract.json')), true);

        $this->assertNotEmpty($contract['customer_endpoints']);
        $this->assertNotEmpty($contract['admin_endpoints']);
        $this->assertContains('POST /customer/wallet/deposits/{walletTransaction}/proof', $contract['customer_endpoints']);
        $this->assertContains('POST /wallet-deposits/{walletTransaction}/confirm', $contract['admin_endpoints']);
        $this->assertContains('POST /customer/documents/{generatedDocument}/accept', $contract['customer_endpoints']);
    }
    public function test_declared_endpoints_exist_in_laravel_router(): void
    {
        $contract = json_decode((string) file_get_contents(resource_path('contracts/marketplace-contract.json')), true);
        $routes = collect(app('router')->getRoutes()->getRoutes())->flatMap(function ($route) {
            $uri = '/'.preg_replace('#^api/v1/?#', '', $route->uri());
            return collect($route->methods())->reject(fn ($method) => $method === 'HEAD')->map(fn ($method) => $method.' '.$uri);
        })->values();

        $normalize = fn (string $value) => preg_replace('/\{[^}]+\}/', '{param}', $value);
        $normalizedRoutes = $routes->map($normalize)->all();

        foreach (array_merge($contract['public_endpoints'], $contract['customer_endpoints'], $contract['admin_endpoints']) as $declared) {
            $this->assertContains($normalize($declared), $normalizedRoutes, 'Missing declared route: '.$declared);
        }
    }

}
