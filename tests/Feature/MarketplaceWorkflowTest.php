<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function customerHeaders(Customer $customer): array
    {
        return ['Authorization' => 'Bearer '.auth('customer_api')->login($customer)];
    }

    private function adminHeaders(): array
    {
        $admin = User::factory()->create(['username' => 'admin-marketplace']);

        return ['Authorization' => 'Bearer '.auth('api')->login($admin)];
    }

    public function test_customer_purchase_installment_payment_and_handover_flow(): void
    {
        $buyer = Customer::factory()->create();
        $seller = Customer::factory()->create();
        $product = Product::create([
            'code' => 'NSO-1001',
            'name' => 'Ninja School 1001',
            'product_type' => 'game_account',
            'game_code' => 'ninja_school',
            'status' => 'active',
            'price' => 900000,
            'owner_customer_id' => $seller->id,
        ]);
        $listing = ProductListing::create([
            'code' => 'LST-NSO-1001',
            'product_id' => $product->id,
            'owner_customer_id' => $seller->id,
            'listing_type' => 'sale',
            'status' => 'published',
            'title' => 'Bán Ninja School 1001',
            'sale_price' => 900000,
            'deposit_amount' => 100000,
            'allow_installment' => true,
            'max_installment_count' => 3,
            'minimum_initial_payment' => 400000,
            'published_at' => now(),
        ]);

        $transaction = $this->postJson('/api/v1/customer/listings/'.$listing->id.'/transact', [
            'purchase_mode' => 'installment',
            'initial_payment_amount' => 400000,
            'installment_count' => 3,
            'payment_method' => 'bank',
        ], $this->customerHeaders($buyer))
            ->assertCreated()
            ->assertJsonPath('data.purchase_mode', 'installment')
            ->assertJsonCount(3, 'data.payments')
            ->json('data');

        $paymentId = $transaction['payments'][0]['id'];
        $this->postJson('/api/v1/customer/transactions/'.$transaction['id'].'/payments/'.$paymentId.'/submit', [
            'payment_method' => 'bank',
            'reference' => 'BANK-REF-001',
        ], $this->customerHeaders($buyer))->assertOk()->assertJsonPath('data.status', 'submitted');

        $this->postJson('/api/v1/payments/'.$paymentId.'/confirm', [], $this->adminHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->postJson('/api/v1/customer/transactions/'.$transaction['id'].'/actions', [
            'action' => 'seller_handover',
        ], $this->customerHeaders($seller))->assertOk()->assertJsonPath('data.status','handover_pending');

        $this->postJson('/api/v1/customer/transactions/'.$transaction['id'].'/actions', [
            'action' => 'buyer_receive',
        ], $this->customerHeaders($buyer))->assertOk()->assertJsonPath('data.status','handed_over');
    }

    public function test_customer_can_open_dispute(): void
    {
        $buyer = Customer::factory()->create();
        $seller = Customer::factory()->create();
        $product = Product::create([
            'code' => 'NSO-1002',
            'name' => 'Ninja School 1002',
            'status' => 'active',
            'price' => 500000,
            'owner_customer_id' => $seller->id,
        ]);
        $listing = ProductListing::create([
            'code' => 'LST-NSO-1002',
            'product_id' => $product->id,
            'owner_customer_id' => $seller->id,
            'listing_type' => 'sale',
            'status' => 'published',
            'title' => 'Bán Ninja School 1002',
            'sale_price' => 500000,
            'published_at' => now(),
        ]);
        $transactionId = $this->postJson('/api/v1/customer/listings/'.$listing->id.'/transact', [], $this->customerHeaders($buyer))
            ->assertCreated()->json('data.id');

        $this->postJson('/api/v1/customer/transactions/'.$transactionId.'/disputes', [
            'reason' => 'not_as_described',
            'description' => 'Thông tin bàn giao không đúng nội dung tin đăng.',
            'evidence' => ['https://example.com/evidence.png'],
        ], $this->customerHeaders($buyer))
            ->assertCreated()
            ->assertJsonPath('data.status', 'open');
    }
}
