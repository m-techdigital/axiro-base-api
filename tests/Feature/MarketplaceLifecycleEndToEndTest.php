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

    public function test_rental_reaches_active_returned_and_completed_terminal_state(): void
    {
        [$transaction, $payments] = $this->rentalFixture();
        $headers = $this->adminHeaders();

        foreach ($payments as $payment) {
            $this->postJson('/api/v1/payments/'.$payment->id.'/confirm', [], $headers)->assertOk();
        }

        $this->postJson('/api/v1/transactions/'.$transaction->id.'/actions', [
            'action' => 'force_handover',
            'note' => 'Đã xác minh bàn giao tài khoản thuê.',
        ], $headers)->assertOk()->assertJsonPath('data.status', 'active');

        $this->postJson('/api/v1/transactions/'.$transaction->id.'/actions', [
            'action' => 'force_return',
            'note' => 'Đã xác minh hoàn trả tài khoản thuê.',
        ], $headers)->assertOk()->assertJsonPath('data.status', 'returned');

        $this->postJson('/api/v1/transactions/'.$transaction->id.'/actions', [
            'action' => 'complete',
            'note' => 'Đã quyết toán giao dịch thuê.',
        ], $headers)->assertOk()->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'status' => 'completed']);
        $this->assertDatabaseHas('transaction_payments', [
            'transaction_id' => $transaction->id,
            'component_type' => 'security_deposit',
            'settlement_status' => 'refunded',
        ]);
        $this->assertDatabaseHas('transaction_payments', [
            'transaction_id' => $transaction->id,
            'component_type' => 'rental_fee',
            'settlement_status' => 'released',
        ]);
    }

    public function test_rental_dispute_can_cancel_and_refund_all_held_payments(): void
    {
        [$transaction, $payments] = $this->rentalFixture();
        $headers = $this->adminHeaders();

        foreach ($payments as $payment) {
            $this->postJson('/api/v1/payments/'.$payment->id.'/confirm', [], $headers)->assertOk();
        }

        $this->postJson('/api/v1/transactions/'.$transaction->id.'/actions', [
            'action' => 'force_handover',
            'note' => 'Đã xác minh bàn giao tài khoản thuê.',
        ], $headers)->assertOk()->assertJsonPath('data.status', 'active');

        $dispute = MarketplaceDispute::query()->create([
            'code' => 'DSP-E2E-RENT-'.str()->upper(str()->random(4)),
            'transaction_id' => $transaction->id,
            'opened_by_customer_id' => $transaction->buyer_customer_id,
            'reason' => 'return_issue',
            'description' => 'Tài khoản thuê phát sinh lỗi nghiêm trọng.',
            'status' => 'open',
        ]);
        $transaction->update(['status' => 'disputed']);

        $this->postJson('/api/v1/disputes/'.$dispute->id.'/resolve', [
            'status' => 'resolved',
            'resolution' => 'Hủy giao dịch thuê và hoàn toàn bộ khoản đang tạm giữ.',
            'outcome' => 'cancel_refund',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.outcome', 'cancel_refund')
            ->assertJsonPath('data.transaction.status', 'cancelled');

        foreach ($payments as $payment) {
            $this->assertDatabaseHas('transaction_payments', [
                'id' => $payment->id,
                'settlement_status' => 'refunded',
            ]);
        }
        $this->assertSame(2, MarketplaceNotification::query()->where('type', 'dispute_outcome')->count());
    }

    public function test_rental_overdue_is_marked_and_notifies_both_parties(): void
    {
        [$transaction] = $this->rentalFixture();
        $transaction->update([
            'status' => 'active',
            'rental_end_at' => now()->subMinute(),
        ]);

        $this->artisan('marketplace:scan-due')->assertSuccessful();

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'overdue',
        ]);
        $this->assertSame(
            2,
            MarketplaceNotification::query()
                ->where('type', 'rental_overdue')
                ->where('transaction_id', $transaction->id)
                ->count(),
        );
    }

    public function test_rental_return_can_complete_with_partial_deposit_deduction(): void
    {
        [$transaction, $payments] = $this->rentalFixture();
        $headers = $this->adminHeaders();

        foreach ($payments as $payment) {
            $this->postJson('/api/v1/payments/'.$payment->id.'/confirm', [], $headers)->assertOk();
        }

        $this->postJson('/api/v1/transactions/'.$transaction->id.'/actions', [
            'action' => 'force_handover',
            'note' => 'Đã xác minh bàn giao tài khoản thuê.',
        ], $headers)->assertOk();

        $this->postJson('/api/v1/transactions/'.$transaction->id.'/actions', [
            'action' => 'force_return',
            'note' => 'Đã xác minh hoàn trả tài khoản thuê.',
        ], $headers)->assertOk();

        $this->postJson('/api/v1/transactions/'.$transaction->id.'/actions', [
            'action' => 'complete',
            'note' => 'Hoàn tất giao dịch thuê và khấu trừ chi phí khôi phục.',
            'rental_deposit_deduction_amount' => 100000,
            'rental_deposit_deduction_note' => 'Khấu trừ chi phí khôi phục bảo mật có bằng chứng.',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.rental_deposit_deduction_amount', '100000.00');

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'completed',
            'rental_deposit_deduction_amount' => 100000,
            'refunded_amount' => 300000,
            'released_amount' => 850000,
        ]);
        $this->assertDatabaseHas('transaction_payments', [
            'transaction_id' => $transaction->id,
            'component_type' => 'security_deposit',
            'settlement_status' => 'partially_refunded',
        ]);
        $this->assertDatabaseHas('transaction_events', [
            'transaction_id' => $transaction->id,
            'event_type' => 'admin_complete',
        ]);
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

    private function rentalFixture(): array
    {
        $renter = Customer::factory()->create();
        $lessor = Customer::factory()->create();
        CustomerWallet::query()->create(['customer_id' => $renter->id, 'available_balance' => 2000000]);
        CustomerWallet::query()->create(['customer_id' => $lessor->id]);
        $product = Product::query()->create([
            'code' => 'E2E-RENT-'.str()->upper(str()->random(4)),
            'name' => 'Tài khoản thuê kiểm thử',
            'product_type' => 'game_account',
            'game_code' => 'ninja_school',
            'owner_customer_id' => $lessor->id,
            'status' => 'active',
            'approval_status' => 'approved',
            'is_published' => true,
            'availability_status' => 'available',
            'rental_price' => 750000,
            'rental_deposit_amount' => 400000,
        ]);
        $product->syncOfferModes(['rent']);
        $transaction = Transaction::query()->create([
            'code' => 'TRX-E2E-RENT-'.str()->upper(str()->random(4)),
            'transaction_type' => 'rental',
            'purchase_mode' => 'rental',
            'product_id' => $product->id,
            'buyer_customer_id' => $renter->id,
            'seller_customer_id' => $lessor->id,
            'transaction_value' => 750000,
            'deposit_amount' => 400000,
            'total_payable' => 1150000,
            'seller_net_amount' => 750000,
            'transaction_date' => now()->toDateString(),
            'rental_start_at' => now(),
            'rental_end_at' => now()->addDays(3),
            'status' => 'pending_payment',
        ]);
        $payments = collect([
            TransactionPayment::query()->create([
                'code' => 'PAY-E2E-DEP-'.str()->upper(str()->random(4)),
                'transaction_id' => $transaction->id,
                'customer_id' => $renter->id,
                'payment_type' => 'security_deposit',
                'component_type' => 'security_deposit',
                'amount' => 400000,
                'refundable' => true,
                'status' => 'submitted',
                'settlement_status' => 'unsettled',
                'payment_method' => 'bank',
            ]),
            TransactionPayment::query()->create([
                'code' => 'PAY-E2E-RENT-'.str()->upper(str()->random(4)),
                'transaction_id' => $transaction->id,
                'customer_id' => $renter->id,
                'payment_type' => 'rental_cycle',
                'component_type' => 'rental_fee',
                'amount' => 750000,
                'refundable' => false,
                'status' => 'submitted',
                'settlement_status' => 'unsettled',
                'payment_method' => 'bank',
            ]),
        ]);

        return [$transaction, $payments];
    }
}
