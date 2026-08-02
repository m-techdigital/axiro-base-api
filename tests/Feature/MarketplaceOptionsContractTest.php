<?php

namespace Tests\Feature;

use App\Support\Marketplace\MarketplaceOptionsCatalog;
use App\Support\MarketplaceContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceOptionsContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketplace_options_expose_canonical_catalog_and_cache_metadata(): void
    {
        $response = $this->getJson('/api/v1/marketplace/options')
            ->assertOk()
            ->assertJsonPath('meta.options_version', MarketplaceOptionsCatalog::VERSION)
            ->assertJsonPath('meta.options_hash', MarketplaceOptionsCatalog::hash())
            ->assertJsonPath('meta.cache_ttl_seconds', MarketplaceOptionsCatalog::CACHE_TTL_SECONDS)
            ->assertJsonPath('meta.contract_version', MarketplaceContract::version())
            ->assertJsonPath('meta.contract_hash', MarketplaceContract::hash())
            ->assertJsonFragment(['value' => 'sale_record'])
            ->assertJsonFragment(['value' => 'rental_record'])
            ->assertJsonFragment(['value' => 'cancel_refund']);

        $response->assertHeader('X-Marketplace-Options-Version', MarketplaceOptionsCatalog::VERSION);
        $response->assertHeader('X-Marketplace-Options-Hash', MarketplaceOptionsCatalog::hash());
        $response->assertHeader('ETag', '"'.MarketplaceOptionsCatalog::hash().'"');
        $response->assertHeader('X-Marketplace-Contract-Version', MarketplaceContract::version());
    }

    public function test_marketplace_options_support_etag_revalidation(): void
    {
        $this->withHeader('If-None-Match', '"'.MarketplaceOptionsCatalog::hash().'"')
            ->get('/api/v1/marketplace/options')
            ->assertStatus(304)
            ->assertHeader('X-Marketplace-Options-Version', MarketplaceOptionsCatalog::VERSION)
            ->assertHeader('X-Marketplace-Contract-Version', MarketplaceContract::version());
    }
}
