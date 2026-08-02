<?php

namespace Tests\Feature;

use App\Enums\ProductAvailabilityStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Transaction;
use App\Services\ProductAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductAvailabilityLifecycleHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_hold_is_released_once_and_version_is_incremented(): void
    {
        $customer = Customer::factory()->create();
        $product = Product::query()->create(['code' => 'HOLD-001', 'name' => 'Hold', 'product_type' => 'game_account', 'game_code' => 'ninja_school', 'status' => 'active', 'availability_status' => 'available', 'availability_version' => 1]);
        $transaction = Transaction::query()->create(['code' => 'TRX-HOLD-001', 'idempotency_key' => 'hold-test', 'request_hash' => str_repeat('a', 64), 'transaction_type' => 'purchase', 'purchase_mode' => 'full', 'product_id' => $product->id, 'buyer_customer_id' => $customer->id, 'seller_customer_id' => $customer->id, 'transaction_value' => 1, 'total_payable' => 1, 'transaction_date' => now()->toDateString(), 'status' => 'pending_payment']);
        $service = app(ProductAvailabilityService::class);
        $held = $service->hold($product, $customer->id, $transaction, 1, null, 1);
        $held->holds()->where('status', 'active')->update(['hold_until' => now()->subMinute()]);
        $held->update(['hold_expires_at' => now()->subMinute()]);

        $this->assertSame(1, $service->expireHolds());
        $this->assertSame(0, $service->expireHolds());
        $this->assertDatabaseHas('products', ['id' => $product->id, 'availability_status' => ProductAvailabilityStatus::AVAILABLE->value, 'availability_version' => 3]);
    }
}
