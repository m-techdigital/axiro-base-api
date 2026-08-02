<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Models\MarketplaceDispute;
use App\Models\MarketplaceNotification;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceLifecycleEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_sale_reaches_completed_terminal_state(): void
    {
        [$transaction, $payment] = $this->fixture();
        $headers = $this->adminHeaders();

        $this->postJson('/api/v1/payments/'.$payment->id.'/confirm', [], $headers)->assertOk();
        $this->postJson('/api/v1/transactions/'.$transaction->id.'/actions', ['action' => 'force_handover', 'note' => 'Đã xác minh bàn giao.'], $headers)->assertOk();
        $this->postJson('/api/v1/transactions/'.$transaction->id.'/actions', ['action' => 'complete', 'note' => 'Hai bên đã hoàn tất.'], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'status' => 'completed']);
        $this->assertDatabaseHas('transaction_events', ['transaction_id' => $transaction->id, 'event_type' => 'admin_complete']);
    }

    public function test_dispute_refund_reaches_cancelled_terminal_state_and_notifies_both_parties(): void
    {
        [$transaction, $payment] = $this->fixture();
        $headers = $this->adminHeaders();
        $this->postJson('/api/v1/payments/'.$payment->id.'/confirm', [], $headers)->assertOk();
        $dispute = MarketplaceDispute::query()->create([
            'code' => 'DSP-E2E-REFUND',
            'transaction_id' => $transaction->id,
            'opened_by_customer_id' => $transaction->buyer_customer_id,
            'reason' => 'not_as_described',
            'description' => 'Sản phẩm không đúng mô tả.',
            'status' => 'open',
        ]);
        $transaction->update(['status' => 'disputed']);

        $this->postJson('/api/v1/disputes/'.$dispute->id.'/resolve', [
            'status' => 'resolved',
            'resolution' => 'Chấp nhận tranh chấp và hoàn khoản đang giữ cho người mua.',
            'outcome' => 'cancel_refund',
        ], $headers)->assertOk()->assertJsonPath('data.transaction.status', 'cancelled');

        $this->assertDatabaseHas('transaction_payments', ['id' => $payment->id, 'settlement_status' => 'refunded']);
        $this->assertSame(2, MarketplaceNotification::query()->where('type', 'dispute_outcome')->count());
        $this->assertDatabaseHas('transaction_events', ['transaction_id' => $transaction->id, 'event_type' => 'dispute_resolved']);
    }

    private function adminHeaders(): array
    {
        return ['Authorization' => 'Bearer '.auth('api')->login(User::factory()->create())];
    }

    private function fixture(): array
    {
        $buyer = Customer::factory()->create();
        $seller = Customer::factory()->create();
        CustomerWallet::query()->create(['customer_id' => $buyer->id, 'available_balance' => 500000]);
        CustomerWallet::query()->create(['customer_id' => $seller->id]);
        $product = Product::query()->create([
            'code' => 'E2E-PRODUCT-'.str()->upper(str()->random(4)),
            'name' => 'Sản phẩm kiểm thử vòng đời',
            'product_type' => 'game_account',
            'game_code' => 'ninja_school',
            'owner_customer_id' => $seller->id,
            'status' => 'active',
            'approval_status' => 'approved',
            'is_published' => true,
            'availability_status' => 'available',
            'sale_price' => 500000,
        ]);
        $product->syncOfferModes(['sell']);
        $transaction = Transaction::query()->create([
            'code' => 'TRX-E2E-'.str()->upper(str()->random(4)),
            'transaction_type' => 'purchase',
            'purchase_mode' => 'full',
            'product_id' => $product->id,
            'buyer_customer_id' => $buyer->id,
            'seller_customer_id' => $seller->id,
            'transaction_value' => 500000,
            'total_payable' => 500000,
            'seller_net_amount' => 500000,
            'transaction_date' => now()->toDateString(),
            'status' => 'pending_payment',
        ]);
        $payment = TransactionPayment::query()->create([
            'code' => 'PAY-E2E-'.str()->upper(str()->random(4)),
            'transaction_id' => $transaction->id,
            'customer_id' => $buyer->id,
            'payment_type' => 'full',
            'component_type' => 'principal',
            'amount' => 500000,
            'refundable' => true,
            'status' => 'submitted',
            'settlement_status' => 'unsettled',
            'payment_method' => 'wallet',
        ]);

        return [$transaction, $payment];
    }
}
