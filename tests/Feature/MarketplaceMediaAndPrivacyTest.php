<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductListing;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MarketplaceMediaAndPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private function customerHeaders(Customer $customer): array
    {
        return ['Authorization' => 'Bearer '.auth('customer_api')->login($customer)];
    }

    public function test_customer_can_upload_product_images(): void
    {
        Storage::fake('public');
        $customer = Customer::factory()->create();

        $response = $this->postJson('/api/v1/customer/media/images', [
            'images' => [UploadedFile::fake()->image('account.webp', 1200, 800)->size(600)],
        ], $this->customerHeaders($customer));

        $response->assertCreated()->assertJsonCount(1, 'data');
        $path = $response->json('data.0.path');
        Storage::disk('public')->assertExists($path);
    }

    public function test_customer_transaction_response_does_not_expose_internal_audit_history(): void
    {
        $buyer = Customer::factory()->create();
        $seller = Customer::factory()->create();
        $product = Product::create([
            'code' => 'PRIVACY-001',
            'name' => 'Tài khoản kiểm tra quyền riêng tư',
            'product_type' => 'game_account',
            'game_code' => 'ninja_school',
            'status' => 'active',
            'price' => 100000,
            'owner_customer_id' => $seller->id,
        ]);
        $listing = ProductListing::create([
            'code' => 'LST-PRIVACY-001',
            'product_id' => $product->id,
            'owner_customer_id' => $seller->id,
            'listing_type' => 'sale',
            'status' => 'published',
            'title' => 'Tin kiểm tra quyền riêng tư',
            'sale_price' => 100000,
            'installment_interval_unit' => 'week',
            'installment_interval_count' => 1,
            'published_at' => now(),
        ]);
        $transaction = Transaction::create([
            'code' => 'TRX-PRIVACY-001',
            'transaction_type' => 'purchase',
            'listing_id' => $listing->id,
            'product_id' => $product->id,
            'buyer_customer_id' => $buyer->id,
            'seller_customer_id' => $seller->id,
            'transaction_value' => 100000,
            'service_fee' => 0,
            'discount' => 0,
            'deposit_amount' => 0,
            'total_payable' => 100000,
            'paid_amount' => 0,
            'status' => 'pending_payment',
            'transaction_date' => now()->toDateString(),
        ]);

        $this->getJson('/api/v1/customer/transactions/'.$transaction->id, $this->customerHeaders($buyer))
            ->assertOk()
            ->assertJsonMissingPath('data.audit_history');
    }
}
