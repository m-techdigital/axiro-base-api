<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplacePendingPaymentVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function headers(Customer $customer): array
    {
        return ['Authorization' => 'Bearer '.auth('customer_api')->login($customer)];
    }

    private function adminHeaders(): array
    {
        $admin = User::factory()->create(['username' => 'admin-pending-payment']);

        return ['Authorization' => 'Bearer '.auth('api')->login($admin)];
    }

    private function listing(Customer $seller): ProductListing
    {
        $product = Product::create([
            'code' => 'NSO-PENDING-001',
            'name' => 'Ninja School Pending Payment',
            'product_type' => 'game_account',
            'game_code' => 'ninja_school',
            'status' => 'active',
            'price' => 500000,
            'owner_customer_id' => $seller->id,
        ]);

        return ProductListing::create([
            'code' => 'LST-PENDING-001',
            'product_id' => $product->id,
            'owner_customer_id' => $seller->id,
            'listing_type' => 'sale',
            'status' => 'published',
            'title' => 'Tin chờ thanh toán',
            'sale_price' => 500000,
            'published_at' => now(),
        ]);
    }

    public function test_creating_unpaid_transaction_does_not_hide_listing_and_qr_is_available(): void
    {
        $buyer = Customer::factory()->create();
        $seller = Customer::factory()->create();
        $listing = $this->listing($seller);

        $transaction = $this->postJson('/api/v1/customer/listings/'.$listing->id.'/transact', [], $this->headers($buyer))
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_payment')
            ->json('data');

        $this->assertDatabaseHas('product_listings', [
            'id' => $listing->id,
            'status' => 'published',
        ]);

        $this->getJson('/api/v1/marketplace/listings/'.$listing->code, $this->headers($buyer))
            ->assertOk()
            ->assertJsonPath('data.code', $listing->code);

        $payment = $transaction['payments'][0];
        $this->getJson('/api/v1/customer/transactions/'.$transaction['id'].'/payments/'.$payment['id'].'/qr', $this->headers($buyer))
            ->assertOk();
    }

    public function test_listing_is_reserved_only_after_payment_is_confirmed(): void
    {
        $buyer = Customer::factory()->create();
        $seller = Customer::factory()->create();
        $listing = $this->listing($seller);

        $transaction = $this->postJson('/api/v1/customer/listings/'.$listing->id.'/transact', [], $this->headers($buyer))
            ->assertCreated()
            ->json('data');

        $paymentId = $transaction['payments'][0]['id'];
        $this->postJson('/api/v1/customer/transactions/'.$transaction['id'].'/payments/'.$paymentId.'/submit', [
            'payment_method' => 'bank',
            'reference' => 'BANK-PENDING-001',
        ], $this->headers($buyer))->assertOk();

        $this->postJson('/api/v1/payments/'.$paymentId.'/confirm', [], $this->adminHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('product_listings', [
            'id' => $listing->id,
            'status' => 'reserved',
        ]);

        $this->getJson('/api/v1/marketplace/listings/'.$listing->code, $this->headers($buyer))
            ->assertOk()
            ->assertJsonPath('data.code', $listing->code);
    }
}
