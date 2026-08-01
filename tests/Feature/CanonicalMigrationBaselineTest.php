<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CanonicalMigrationBaselineTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_schema_is_product_owned_without_compatibility_migrations(): void
    {
        $this->assertTrue(Schema::hasTable('products'));
        $this->assertTrue(Schema::hasTable('offer_modes'));
        $this->assertTrue(Schema::hasTable('model_offer_modes'));
        $this->assertTrue(Schema::hasTable('product_rental_rates'));
        $this->assertTrue(Schema::hasTable('product_holds'));
        $this->assertTrue(Schema::hasTable('product_availability_histories'));
        $this->assertTrue(Schema::hasColumn('transactions', 'product_id'));
        $this->assertFalse(Schema::hasColumn('transactions', 'listing_id'));
        $this->assertTrue(Schema::hasColumn('marketplace_reviews', 'product_id'));
        $this->assertTrue(Schema::hasTable('product_favorites'));
        $this->assertFalse(Schema::hasTable('product_listings'));
        $this->assertFalse(Schema::hasTable('listing_favorites'));

        $this->assertFileDoesNotExist(database_path('migrations/2026_08_01_000001_merge_product_listings_into_products.php'));
        $this->assertFileDoesNotExist(database_path('migrations/2026_08_01_000002_align_products_with_parent_offer_modes.php'));
    }
}
