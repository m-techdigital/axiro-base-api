<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BaseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function token(): string
    {
        $user = User::factory()->create([
            'username' => 'admin',
            'password' => 'password',
        ]);

        return auth('api')->login($user);
    }

    public function test_product_transaction_flow_does_not_publish_contract_crud(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->token()];
        $buyer = Customer::factory()->create();
        $seller = Customer::factory()->create();

        $product = $this->postJson('/api/v1/products', [
            'code' => 'P-001',
            'name' => 'Product',
            'product_type' => 'game_account',
            'game_code' => 'ninja_school',
            'offer_modes' => ['sell'],
            'status' => 'active',
            'sale_price' => 100,
        ], $headers)->assertCreated()->json('data');

        $transaction = $this->postJson('/api/v1/transactions', [
            'code' => 'T-001',
            'transaction_type' => 'purchase',
            'product_id' => $product['id'],
            'buyer_customer_id' => $buyer->id,
            'seller_customer_id' => $seller->id,
            'transaction_value' => 100,
            'service_fee' => 10,
            'discount' => 5,
            'sale_deposit_amount' => 0,
            'paid_amount' => 0,
            'transaction_date' => '2026-07-29',
            'status' => 'pending_payment',
        ], $headers)
            ->assertCreated()
            ->assertJsonPath('data.total_payable', '105.00')
            ->assertJsonPath('data.buyer.id', $buyer->id)
            ->assertJsonPath('data.seller.id', $seller->id)
            ->json('data');

        $this->getJson('/api/v1/transactions/'.$transaction['id'], $headers)
            ->assertOk()
            ->assertJsonPath('data.product.id', $product['id']);

        $this->postJson('/api/v1/contracts', [
            'transaction_id' => $transaction['id'],
        ], $headers)->assertNotFound();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/products')->assertUnauthorized();
    }
}
