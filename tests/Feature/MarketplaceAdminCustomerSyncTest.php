<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceAdminCustomerSyncTest extends TestCase
{
    use RefreshDatabase;

    private function customerHeaders(Customer $customer): array
    {
        return ['Authorization' => 'Bearer '.auth('customer_api')->login($customer)];
    }

    private function adminHeaders(): array
    {
        $admin = User::factory()->create(['username' => 'admin-sync']);

        return ['Authorization' => 'Bearer '.auth('api')->login($admin)];
    }

    public function test_product_payment_handover_and_notifications_are_synchronized(): void
    {
        $buyer = Customer::factory()->create();
        $seller = Customer::factory()->create();
        $product = Product::create(['code' => 'SYNC-001', 'name' => 'Tài khoản đồng bộ', 'product_type' => 'game_account', 'game_code' => 'ninja_school', 'owner_customer_id' => $seller->id, 'status' => 'active', 'approval_status' => 'approved', 'is_published' => true, 'availability_status' => 'available', 'sale_price' => 500000, 'published_at' => now()]);
        $transaction = $this->postJson('/api/v1/customer/products/'.$product->id.'/transact', [], $this->customerHeaders($buyer))->assertCreated()->json('data');
        $paymentId = $transaction['payments'][0]['id'];
        $this->postJson('/api/v1/customer/transactions/'.$transaction['id'].'/payments/'.$paymentId.'/submit', ['payment_method' => 'bank', 'reference' => 'SYNC-BANK'], $this->customerHeaders($buyer))->assertOk();
        $admin = $this->adminHeaders();
        $this->getJson('/api/v1/action-center', $admin)->assertOk()->assertJsonPath('data.counts.submitted_payments', 1);
        $this->postJson('/api/v1/payments/'.$paymentId.'/confirm', [], $admin)->assertOk();
        $this->postJson('/api/v1/customer/transactions/'.$transaction['id'].'/actions', ['action' => 'seller_handover'], $this->customerHeaders($seller))->assertOk()->assertJsonPath('data.status', 'handover_pending');
        $this->postJson('/api/v1/customer/transactions/'.$transaction['id'].'/actions', ['action' => 'buyer_receive'], $this->customerHeaders($buyer))->assertOk()->assertJsonPath('data.status', 'handed_over');
        $this->getJson('/api/v1/customer/notifications?unread=1', $this->customerHeaders($buyer))->assertOk()->assertJsonPath('data.unread_count', fn ($value) => $value >= 1);
        $this->assertDatabaseHas('transaction_checkpoints', ['transaction_id' => $transaction['id'], 'checkpoint' => 'seller_handover']);
        $this->assertDatabaseHas('transaction_checkpoints', ['transaction_id' => $transaction['id'], 'checkpoint' => 'buyer_received']);
    }
}
