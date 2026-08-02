<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MarketplaceNotification;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNotificationAndRentalSettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_notifications_by_transaction_customer_type_and_read_state(): void
    {
        $customer = Customer::factory()->create();
        $transaction = Transaction::create([
            'code' => 'TRX-NOTIFY-001', 'transaction_type' => 'sale', 'purchase_mode' => 'full',
            'product_id' => Product::create(['code' => 'P-NOTIFY', 'name' => 'Sản phẩm', 'product_type' => 'game_account'])->id,
            'buyer_customer_id' => $customer->id, 'seller_customer_id' => Customer::factory()->create()->id,
            'transaction_value' => 1000, 'total_payable' => 1000, 'transaction_date' => now()->toDateString(), 'status' => 'pending_payment',
        ]);
        MarketplaceNotification::create([
            'customer_id' => $customer->id, 'transaction_id' => $transaction->id, 'transaction_code' => $transaction->code,
            'type' => 'payment_submitted', 'title' => 'Thanh toán mới', 'message' => 'Cần đối soát.',
        ]);

        $token = auth('api')->login(User::factory()->create());
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/notifications?transaction_id='.$transaction->id.'&customer_id='.$customer->id.'&type=payment_submitted&read_status=unread')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.transaction_id', $transaction->id)
            ->assertJsonPath('meta.unread_count', 1);
    }

    public function test_rental_settlement_export_contains_deduction_and_dispute_columns(): void
    {
        $token = auth('api')->login(User::factory()->create());
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/api/v1/operations-dashboard/rental-settlements/export');

        $response->assertOk();
        $this->assertStringContainsString('deposit_deduction_amount', $response->streamedContent());
        $this->assertStringContainsString('dispute_outcome', $response->streamedContent());
    }
}
