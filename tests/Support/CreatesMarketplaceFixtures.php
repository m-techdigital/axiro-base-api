<?php

namespace Tests\Support;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductListing;
use App\Models\Transaction;

trait CreatesMarketplaceFixtures
{
    protected function createMarketplaceTransaction(Customer $buyer, Customer $seller, array $overrides = []): Transaction
    {
        $product = Product::query()->create([
            'code' => 'PRD-'.str()->upper(str()->random(8)),
            'name' => 'Tài khoản kiểm thử',
            'product_type' => 'game_account',
            'status' => 'active',
        ]);

        $listing = ProductListing::query()->create([
            'product_id' => $product->id,
            'seller_customer_id' => $seller->id,
            'code' => 'LST-'.str()->upper(str()->random(8)),
            'title' => 'Tin đăng kiểm thử',
            'listing_type' => 'sale',
            'price' => '100000.00',
            'status' => 'published',
        ]);

        return Transaction::query()->create(array_merge([
            'product_id' => $product->id,
            'product_listing_id' => $listing->id,
            'code' => 'TRX-'.str()->upper(str()->random(8)),
            'transaction_type' => 'purchase',
            'purchase_mode' => 'full',
            'buyer_customer_id' => $buyer->id,
            'seller_customer_id' => $seller->id,
            'transaction_value' => '100000.00',
            'total_payable' => '100000.00',
            'seller_net_amount' => '100000.00',
            'transaction_date' => now()->toDateString(),
            'status' => 'pending_payment',
        ], $overrides));
    }
}
