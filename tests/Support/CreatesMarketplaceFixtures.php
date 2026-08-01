<?php

namespace Tests\Support;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Transaction;

trait CreatesMarketplaceFixtures
{
    protected function createMarketplaceProduct(Customer $seller, array $attributes = [], array $offerModes = ['sell']): Product
    {
        $product = Product::query()->create(array_merge([
            'code' => 'PRD-'.str()->upper(str()->random(8)),
            'name' => 'Tài khoản kiểm thử',
            'product_type' => 'game_account',
            'game_code' => 'ninja_school',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_published' => true,
            'availability_status' => 'available',
            'sale_price' => '100000.00',
            'owner_customer_id' => $seller->id,
            'published_at' => now(),
            'approved_at' => now(),
        ], $attributes));

        $product->syncOfferModes($offerModes);

        return $product->fresh('offerModes');
    }

    protected function createMarketplaceTransaction(Customer $buyer, Customer $seller, array $overrides = []): Transaction
    {
        $product = $this->createMarketplaceProduct($seller);

        return Transaction::query()->create(array_merge([
            'product_id' => $product->id,
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
