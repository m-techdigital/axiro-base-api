<?php

namespace Tests\Feature;

use App\Enums\OfferModeCode;
use App\Models\Product;
use Database\Seeders\OfferModeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductOfferConsolidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_can_support_sell_rent_and_installment_capability(): void
    {
        $this->seed(OfferModeSeeder::class);

        $product = Product::query()->create([
            'code' => 'PRD-MULTI-001',
            'name' => 'Sản phẩm đa mục đích',
            'product_type' => 'game_account',
            'game_code' => 'ninja_school',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_published' => true,
            'sale_price' => 1000000,
            'installment_enabled' => true,
            'max_installment_count' => 3,
            'minimum_initial_payment' => 300000,
            'rental_price' => 100000,
            'rental_price_unit' => 'day',
        ]);

        $product->syncOfferModes([
            OfferModeCode::SELL->value,
            OfferModeCode::RENT->value,
        ]);

        $product->refresh();

        $this->assertSame(['rent', 'sell'], collect($product->offer_modes)->sort()->values()->all());
        $this->assertTrue($product->supports('sale'));
        $this->assertTrue($product->supports('rental'));
        $this->assertTrue($product->supports('installment'));
    }

    public function test_listing_domain_files_and_routes_are_removed(): void
    {
        $this->assertFileDoesNotExist(app_path('Models/ProductListing.php'));
        $this->assertFileDoesNotExist(app_path('Http/Controllers/ListingController.php'));
        $this->assertFileDoesNotExist(database_path('migrations/2026_01_01_000150_create_product_listings_table.php'));

        $routes = file_get_contents(base_path('routes/api/public.php'))
            .file_get_contents(base_path('routes/api/customer.php'));

        $this->assertStringNotContainsString('marketplace/listings', $routes);
        $this->assertStringNotContainsString('customer/listings', $routes);
        $this->assertStringContainsString('marketplace/products', $routes);
        $this->assertStringContainsString('products/{product}/transact', $routes);
    }
}
