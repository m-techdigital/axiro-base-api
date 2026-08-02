<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Models\MarketplaceDispute;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTransactionDetailActionsTest extends TestCase
{
    use RefreshDatabase;

    private function adminHeaders(): array
    {
        $admin = User::factory()->create();

        return ['Authorization' => 'Bearer '.auth('api')->login($admin)];
    }

    public function test_admin_can_confirm_payment_then_handover_and_complete_from_transaction_detail(): void
    {
        [$transaction, $payment] = $this->transactionFixture();
        $headers = $this->adminHeaders();

        $this->postJson('/api/v1/payments/'.$payment->id.'/confirm', [], $headers)
            ->assertOk();

        $this->getJson('/api/v1/transactions/'.$transaction->id, $headers)
            ->assertOk()
            ->assertJsonPath('data.payments.0.status', 'confirmed')
            ->assertJsonPath('data.admin_actions.0', 'force_handover');

        $this->postJson('/api/v1/transactions/'.$transaction->id.'/actions', [
            'action' => 'force_handover',
            'note' => 'Đã kiểm tra bằng chứng bàn giao.',
        ], $headers)->assertOk()->assertJsonPath('data.status', 'handed_over');

        $this->postJson('/api/v1/transactions/'.$transaction->id.'/actions', [
            'action' => 'complete',
            'note' => 'Hai bên đã xác nhận hoàn tất.',
        ], $headers)->assertOk()->assertJsonPath('data.status', 'completed');
    }

    public function test_admin_cancel_refunds_held_payment_and_closes_transaction(): void
    {
        [$transaction, $payment] = $this->transactionFixture();
        $headers = $this->adminHeaders();
        $this->postJson('/api/v1/payments/'.$payment->id.'/confirm', [], $headers)->assertOk();

        $this->postJson('/api/v1/transactions/'.$transaction->id.'/actions', [
            'action' => 'cancel',
            'note' => 'Hủy sau khi đối chiếu và hoàn khoản đang giữ.',
        ], $headers)->assertOk()->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('transaction_payments', [
            'id' => $payment->id,
            'settlement_status' => 'refunded',
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'refunded_amount' => '500000.00',
        ]);
    }

    public function test_dispute_resolution_requires_explicit_terminal_outcome(): void
    {
        [$transaction, $payment] = $this->transactionFixture();
        $headers = $this->adminHeaders();
        $this->postJson('/api/v1/payments/'.$payment->id.'/confirm', [], $headers)->assertOk();
        $dispute = MarketplaceDispute::query()->create([
            'code' => 'DSP-ADMIN-ACTION',
            'transaction_id' => $transaction->id,
            'opened_by_customer_id' => $transaction->buyer_customer_id,
            'reason' => 'not_as_described',
            'description' => 'Thông tin bàn giao không đúng mô tả.',
            'status' => 'open',
        ]);
        $transaction->update(['status' => 'disputed']);

        $this->postJson('/api/v1/disputes/'.$dispute->id.'/resolve', [
            'status' => 'resolved',
            'resolution' => 'Chấp nhận yêu cầu và hoàn toàn bộ khoản đang giữ.',
            'outcome' => 'cancel_refund',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.transaction.status', 'cancelled')
            ->assertJsonPath('data.outcome', 'cancel_refund');

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'cancelled',
            'refunded_amount' => '500000.00',
        ]);
        $this->assertDatabaseHas('marketplace_disputes', [
            'id' => $dispute->id,
            'outcome' => 'cancel_refund',
        ]);
        $this->assertDatabaseHas('marketplace_notifications', [
            'customer_id' => $transaction->buyer_customer_id,
            'type' => 'dispute_outcome',
        ]);
        $this->assertDatabaseHas('marketplace_notifications', [
            'customer_id' => $transaction->seller_customer_id,
            'type' => 'dispute_outcome',
        ]);
    }

    private function transactionFixture(): array
    {
        $buyer = Customer::factory()->create();
        $seller = Customer::factory()->create();
        CustomerWallet::query()->create(['customer_id' => $buyer->id, 'available_balance' => 500000]);
        CustomerWallet::query()->create(['customer_id' => $seller->id]);
        $product = Product::query()->create([
            'code' => 'ADMIN-ACTION-PRODUCT',
            'name' => 'Sản phẩm kiểm thử thao tác quản trị',
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
            'code' => 'TRX-ADMIN-ACTION',
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
            'code' => 'PAY-ADMIN-ACTION',
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

        return [$transaction, $payment, $buyer, $seller, $product];
    }
}
