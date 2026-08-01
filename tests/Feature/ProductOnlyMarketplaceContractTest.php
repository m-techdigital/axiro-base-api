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
    }
}
