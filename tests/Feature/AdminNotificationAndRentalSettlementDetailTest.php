<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MarketplaceNotification;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNotificationAndRentalSettlementDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_notification_detail_contains_customer_transaction_and_timeline_context(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::factory()->create();
        $product = Product::query()->create([
            'code' => 'NTF-DETAIL-001',
            'name' => 'Sản phẩm thông báo',
            'product_type' => 'game_account',
            'game_code' => 'ninja_school',
            'status' => 'active',
        ]);
        $transaction = Transaction::query()->create([
            'code' => 'TRX-NTF-DETAIL-001',
            'product_id' => $product->id,
            'buyer_customer_id' => $customer->id,
            'seller_customer_id' => Customer::factory()->create()->id,
            'transaction_type' => 'purchase',
            'transaction_value' => 100000,
            'total_payable' => 100000,
            'paid_amount' => 0,
            'transaction_date' => now()->toDateString(),
            'status' => 'pending_payment',
        ]);
        TransactionEvent::query()->create([
            'transaction_id' => $transaction->id,
            'event_type' => 'created',
            'title' => 'Đã tạo giao dịch',
            'description' => 'Giao dịch được khởi tạo.',
        ]);
        $notification = MarketplaceNotification::query()->create([
            'customer_id' => $customer->id,
            'transaction_id' => $transaction->id,
            'transaction_code' => $transaction->code,
            'type' => 'transaction_created',
            'title' => 'Giao dịch mới',
            'message' => 'Có giao dịch mới cần theo dõi.',
        ]);

        $token = auth('api')->login($admin);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/notifications/'.$notification->id)
            ->assertOk()
            ->assertJsonPath('data.customer.id', $customer->id)
            ->assertJsonPath('data.transaction.id', $transaction->id)
            ->assertJsonPath('data.transaction.events.0.event_type', 'created');
    }

    public function test_rental_settlement_export_filters_by_date_customer_and_status(): void
    {
        $admin = User::factory()->create();
        $buyer = Customer::factory()->create();
        $seller = Customer::factory()->create();
        $product = Product::query()->create([
            'code' => 'RENT-EXPORT-001',
            'name' => 'Tài khoản thuê',
            'product_type' => 'game_account',
            'game_code' => 'ninja_school',
            'status' => 'active',
        ]);
        Transaction::query()->create([
            'code' => 'TRX-RENT-EXPORT-001',
            'product_id' => $product->id,
            'buyer_customer_id' => $buyer->id,
            'seller_customer_id' => $seller->id,
            'transaction_type' => 'rental',
            'transaction_value' => 100000,
            'total_payable' => 100000,
            'paid_amount' => 100000,
            'transaction_date' => now()->toDateString(),
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        Transaction::query()->create([
            'code' => 'TRX-RENT-EXPORT-OTHER',
            'product_id' => $product->id,
            'buyer_customer_id' => Customer::factory()->create()->id,
            'seller_customer_id' => $seller->id,
            'transaction_type' => 'rental',
            'transaction_value' => 100000,
            'total_payable' => 100000,
            'paid_amount' => 0,
            'transaction_date' => now()->subMonth()->toDateString(),
            'status' => 'cancelled',
            'completed_at' => now()->subMonth(),
        ]);

        $token = auth('api')->login($admin);
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/api/v1/operations-dashboard/rental-settlements/export?customer_id='.$buyer->id.'&status=completed&date_from='.now()->subDay()->toDateString().'&date_to='.now()->addDay()->toDateString());

        $response->assertOk();
        $this->assertStringContainsString('TRX-RENT-EXPORT-001', $response->streamedContent());
        $this->assertStringNotContainsString('TRX-RENT-EXPORT-OTHER', $response->streamedContent());
    }
}
