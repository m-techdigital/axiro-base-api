<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProductOnlyMarketplaceContractTest extends TestCase
{
    public function test_listing_domain_is_removed_and_product_uses_offer_modes(): void
    {
        $product = file_get_contents(app_path('Models/Product.php'));
        $request = file_get_contents(app_path('Http/Requests/ProductRequest.php'));

        $this->assertStringContainsString('HasOfferModes', $product);
        $this->assertStringContainsString("'installment_enabled'", $product);
        $this->assertStringContainsString("'offer_modes'", $request);
        $this->assertStringNotContainsString("'transaction_types' => 'array'", $product);
        $this->assertFileDoesNotExist(app_path('Models/ProductListing.php'));
        $this->assertFileDoesNotExist(app_path('Models/Contract.php'));
        $this->assertFileDoesNotExist(app_path('Http/Controllers/ContractController.php'));
        $this->assertFileDoesNotExist(app_path('Http/Requests/ContractRequest.php'));
        $this->assertFileDoesNotExist(database_path('migrations/2026_01_01_000300_create_contracts_table.php'));
    }
}
