<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceTransactionIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function customerHeaders(Customer $customer): array
    {
        $token = auth('customer_api')->login($customer);

        return ['Authorization' => 'Bearer '.$token];
    }

    public function test_same_key_and_payload_returns_same_transaction_without_duplicate_payments(): void
    {
        $buyer = Customer::factory()->create();
        $seller = Customer::factory()->create();
        $product = Product::query()->create([
            'code' => 'IDEMP-001', 'name' => 'Sản phẩm', 'product_type' => 'game_account', 'game_code' => 'ninja_school',
            'status' => 'active', 'approval_status' => 'approved', 'is_published' => true, 'availability_status' => 'available',
            'availability_version' => 1, 'sale_price' => 100000, 'owner_customer_id' => $seller->id,
        ]);
        $product->syncOfferModes(['sell']);
        $headers = $this->customerHeaders($buyer);
        $payload = ['idempotency_key' => 'checkout-001', 'availability_version' => 1, 'transaction_type' => 'sale'];

        $first = $this->postJson("/api/v1/customer/products/{$product->id}/transact", $payload, $headers)->assertCreated()->json('data');
        $second = $this->postJson("/api/v1/customer/products/{$product->id}/transact", $payload, $headers)->assertCreated()->json('data');

        $this->assertSame($first['id'], $second['id']);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('transaction_payments', 1);
    }

    public function test_same_key_with_different_payload_is_rejected(): void
    {
        $buyer = Customer::factory()->create();
        $seller = Customer::factory()->create();
        $product = Product::query()->create([
            'code' => 'IDEMP-002', 'name' => 'Sản phẩm', 'product_type' => 'game_account', 'game_code' => 'ninja_school',
            'status' => 'active', 'approval_status' => 'approved', 'is_published' => true, 'availability_status' => 'available',
            'availability_version' => 1, 'sale_price' => 100000, 'owner_customer_id' => $seller->id,
        ]);
        $product->syncOfferModes(['sell']);
        $headers = $this->customerHeaders($buyer);

        $this->postJson("/api/v1/customer/products/{$product->id}/transact", ['idempotency_key' => 'checkout-002', 'availability_version' => 1, 'transaction_type' => 'sale'], $headers)->assertCreated();
        $this->postJson("/api/v1/customer/products/{$product->id}/transact", ['idempotency_key' => 'checkout-002', 'availability_version' => 1, 'transaction_type' => 'sale', 'note' => 'changed'], $headers)->assertUnprocessable();
    }
}
