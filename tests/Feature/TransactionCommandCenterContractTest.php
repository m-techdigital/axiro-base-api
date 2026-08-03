<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionCommandCenterContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_detail_exposes_command_center_and_blocked_reasons(): void
    {
        $admin = User::factory()->create();
        $buyer = Customer::factory()->create();
        $seller = Customer::factory()->create();
        $product = Product::query()->create(['code' => 'CMD-001', 'name' => 'Sản phẩm command center', 'product_type' => 'game_account', 'game_code' => 'ninja_school', 'status' => 'active', 'owner_customer_id' => $seller->id]);
        $transaction = Transaction::query()->create(['code' => 'TRX-CMD-001', 'transaction_type' => 'purchase', 'purchase_mode' => 'full', 'product_id' => $product->id, 'buyer_customer_id' => $buyer->id, 'seller_customer_id' => $seller->id, 'transaction_value' => 100000, 'total_payable' => 100000, 'paid_amount' => 0, 'refunded_amount' => 0, 'escrow_amount' => 0, 'released_amount' => 0, 'wallet_paid_amount' => 0, 'transaction_date' => now()->toDateString(), 'status' => 'pending_payment']);
        TransactionPayment::query()->create(['code' => 'PAY-CMD-001', 'transaction_id' => $transaction->id, 'customer_id' => $buyer->id, 'payment_type' => 'full', 'component_type' => 'principal', 'amount' => 100000, 'status' => 'pending', 'settlement_status' => 'unsettled']);

        $token = auth('api')->login($admin);
        $response = $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/transactions/'.$transaction->id);
        $response->assertOk()
            ->assertJsonPath('data.command_center.lifecycle.status.value', 'pending_payment')
            ->assertJsonPath('data.command_center.lifecycle.next_action.key', 'cancel')
            ->assertJsonPath('data.command_center.lifecycle.guidance.0.key', 'payment')
            ->assertJsonStructure(['data' => ['command_center' => ['lifecycle' => ['actions', 'blocking_reasons', 'guidance'], 'workflow_checklist', 'pending_payments', 'settlement']]]);
    }

    public function test_customer_next_actions_explain_current_step(): void
    {
        $buyer = Customer::factory()->create();
        $seller = Customer::factory()->create();
        $product = Product::query()->create(['code' => 'CMD-002', 'name' => 'Sản phẩm customer journey', 'product_type' => 'game_account', 'game_code' => 'ninja_school', 'status' => 'active', 'owner_customer_id' => $seller->id]);
        $transaction = Transaction::query()->create(['code' => 'TRX-CMD-002', 'transaction_type' => 'purchase', 'purchase_mode' => 'full', 'product_id' => $product->id, 'buyer_customer_id' => $buyer->id, 'seller_customer_id' => $seller->id, 'transaction_value' => 100000, 'total_payable' => 100000, 'paid_amount' => 0, 'refunded_amount' => 0, 'escrow_amount' => 0, 'released_amount' => 0, 'wallet_paid_amount' => 0, 'transaction_date' => now()->toDateString(), 'status' => 'pending_payment']);
        TransactionPayment::query()->create(['code' => 'PAY-CMD-002', 'transaction_id' => $transaction->id, 'customer_id' => $buyer->id, 'payment_type' => 'full', 'component_type' => 'principal', 'amount' => 100000, 'status' => 'pending', 'settlement_status' => 'unsettled']);

        $this->actingAs($buyer, 'customer_api')->getJson('/api/v1/customer/transactions/'.$transaction->id.'/next-actions')
            ->assertOk()
            ->assertJsonPath('data.lifecycle.next_action.key', 'pay')
            ->assertJsonPath('data.amount_due', 100000)
            ->assertJsonPath('data.lifecycle.guidance.0.key', 'payment')
            ->assertJsonStructure(['data' => ['lifecycle' => ['status', 'actions', 'next_action', 'guidance'], 'workflow_checklist']]);
    }

    public function test_customer_cannot_read_another_customers_transaction_or_next_actions(): void
    {
        $buyer = Customer::factory()->create();
        $seller = Customer::factory()->create();
        $outsider = Customer::factory()->create();
        $product = Product::query()->create(['code' => 'CMD-003', 'name' => 'Sản phẩm private', 'product_type' => 'game_account', 'game_code' => 'ninja_school', 'status' => 'active', 'owner_customer_id' => $seller->id]);
        $transaction = Transaction::query()->create(['code' => 'TRX-CMD-003', 'transaction_type' => 'purchase', 'purchase_mode' => 'full', 'product_id' => $product->id, 'buyer_customer_id' => $buyer->id, 'seller_customer_id' => $seller->id, 'transaction_value' => 100000, 'total_payable' => 100000, 'paid_amount' => 0, 'refunded_amount' => 0, 'escrow_amount' => 0, 'released_amount' => 0, 'wallet_paid_amount' => 0, 'transaction_date' => now()->toDateString(), 'status' => 'pending_payment']);

        $this->actingAs($outsider, 'customer_api')
            ->getJson('/api/v1/customer/transactions/'.$transaction->id)
            ->assertForbidden();

        $this->actingAs($outsider, 'customer_api')
            ->getJson('/api/v1/customer/transactions/'.$transaction->id.'/next-actions')
            ->assertForbidden();
    }

    public function test_rental_guidance_exposes_the_same_money_and_deadline_contract_for_admin_and_customer(): void
    {
        $admin = User::factory()->create();
        $renter = Customer::factory()->create();
        $lessor = Customer::factory()->create();
        $product = Product::query()->create([
            'code' => 'CMD-RENT-001',
            'name' => 'Sản phẩm thuê có cọc',
            'product_type' => 'game_account',
            'game_code' => 'ninja_school',
            'status' => 'active',
            'owner_customer_id' => $lessor->id,
        ]);
        $transaction = Transaction::query()->create([
            'code' => 'TRX-CMD-RENT-001',
            'transaction_type' => 'rental',
            'purchase_mode' => 'full',
            'product_id' => $product->id,
            'buyer_customer_id' => $renter->id,
            'seller_customer_id' => $lessor->id,
            'transaction_value' => '300000.00',
            'total_payable' => '500000.00',
            'deposit_amount' => '200000.00',
            'rental_deposit_deduction_amount' => '50000.00',
            'paid_amount' => '500000.00',
            'refunded_amount' => '0.00',
            'escrow_amount' => '200000.00',
            'released_amount' => '300000.00',
            'wallet_paid_amount' => '500000.00',
            'transaction_date' => now()->toDateString(),
            'rental_end_at' => now()->addDay(),
            'status' => 'returned',
        ]);

        $adminToken = auth('api')->login($admin);
        $adminResponse = $this->withToken($adminToken)
            ->getJson('/api/v1/transactions/'.$transaction->id)
            ->assertOk();

        $customerResponse = $this->actingAs($renter, 'customer_api')
            ->getJson('/api/v1/customer/transactions/'.$transaction->id.'/next-actions')
            ->assertOk();

        foreach ([$adminResponse, $customerResponse] as $response) {
            $guidance = collect($response->json('data.command_center.lifecycle.guidance')
                ?? $response->json('data.lifecycle.guidance'))
                ->firstWhere('key', 'rental_settlement');
            $this->assertSame('300000.00', $guidance['rental_amount']);
            $this->assertSame('200000.00', $guidance['deposit_amount']);
            $this->assertSame('50000.00', $guidance['deduction_amount']);
            $this->assertSame('150000.00', $guidance['refundable_amount']);
            $this->assertNotEmpty($guidance['due_at']);
        }
    }
}
