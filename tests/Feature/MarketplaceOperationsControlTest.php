<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductHold;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ProductAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceOperationsControlTest extends TestCase
{
    use RefreshDatabase;

    private function adminHeaders(): array
    {
        $user = User::factory()->create();
        $token = auth('api')->login($user);

        return ['Authorization' => 'Bearer '.$token];
    }

    public function test_operations_overview_and_manual_hold_release_are_available_to_admin(): void
    {
        $buyer = Customer::factory()->create();
        $seller = Customer::factory()->create();
        $product = Product::query()->create([
            'code' => 'OPS-001',
            'name' => 'Sản phẩm vận hành',
            'product_type' => 'game_account',
            'game_code' => 'ninja_school',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_published' => true,
            'availability_status' => 'available',
            'availability_version' => 1,
            'sale_price' => 100000,
            'owner_customer_id' => $seller->id,
        ]);
        $product->syncOfferModes(['sell']);
        $transaction = Transaction::query()->create([
            'code' => 'TRX-OPS-001',
            'idempotency_key' => 'ops-hold-001',
            'request_hash' => str_repeat('a', 64),
            'transaction_type' => 'purchase',
            'purchase_mode' => 'full',
            'product_id' => $product->id,
            'buyer_customer_id' => $buyer->id,
            'seller_customer_id' => $seller->id,
            'transaction_value' => 100000,
            'total_payable' => 100000,
            'transaction_date' => now()->toDateString(),
            'status' => 'pending_payment',
        ]);
        app(ProductAvailabilityService::class)->hold($product, $buyer->id, $transaction, 30, 'Giữ để kiểm tra vận hành', 1);
        $hold = ProductHold::query()->firstOrFail();
        $headers = $this->adminHeaders();

        $this->getJson('/api/v1/operations-dashboard/overview', $headers)
            ->assertOk()
            ->assertJsonPath('data.holds.active', 1)
            ->assertJsonPath('data.menu_counters.expired_holds', 0)
            ->assertJsonPath('data.menu_counters.pending_payment_confirmation', 0)
            ->assertJsonPath('data.menu_counters.open_disputes', 0);

        $this->getJson('/api/v1/operations-dashboard/transactions/'.$transaction->id.'/document-checklist', $headers)
            ->assertOk()
            ->assertJsonPath('data.0.key', 'payment')
            ->assertJsonPath('data.1.key', 'handover')
            ->assertJsonPath('data.2.key', 'acceptance')
            ->assertJsonPath('data.3.key', 'dispute');

        $this->postJson('/api/v1/operations-dashboard/holds/'.$hold->id.'/release', [
            'note' => 'Admin đã xác minh giao dịch không còn hiệu lực.',
            'expected_version' => 2,
        ], $headers)->assertOk()->assertJsonPath('data.availability_status', 'available');

        $this->assertDatabaseHas('product_holds', [
            'id' => $hold->id,
            'status' => 'released',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'product_hold_manual_release',
            'entity_id' => (string) $hold->id,
        ]);
    }

    public function test_idempotency_replay_is_visible_in_audit_feed(): void
    {
        $buyer = Customer::factory()->create();
        $seller = Customer::factory()->create();
        $product = Product::query()->create([
            'code' => 'OPS-IDEMP-001',
            'name' => 'Sản phẩm chống trùng',
            'product_type' => 'game_account',
            'game_code' => 'ninja_school',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_published' => true,
            'availability_status' => 'available',
            'availability_version' => 1,
            'sale_price' => 100000,
            'owner_customer_id' => $seller->id,
        ]);
        $product->syncOfferModes(['sell']);
        $customerToken = auth('customer_api')->login($buyer);
        $customerHeaders = ['Authorization' => 'Bearer '.$customerToken];
        $payload = [
            'idempotency_key' => 'ops-checkout-001',
            'availability_version' => 1,
            'transaction_type' => 'sale',
        ];

        $this->postJson('/api/v1/customer/products/'.$product->id.'/transact', $payload, $customerHeaders)->assertCreated();
        $this->postJson('/api/v1/customer/products/'.$product->id.'/transact', $payload, $customerHeaders)->assertCreated();

        $this->getJson('/api/v1/operations-dashboard/idempotency', $this->adminHeaders())
            ->assertOk()
            ->assertJsonPath('data.0.event_type', 'checkout_idempotent_replay');
    }
}
