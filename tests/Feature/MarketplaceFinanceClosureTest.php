<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MarketplaceFinanceClosureTest extends TestCase
{
    use RefreshDatabase;

    private function customerHeaders(Customer $customer): array
    {
        return ['Authorization' => 'Bearer '.auth('customer_api')->login($customer)];
    }

    private function adminHeaders(): array
    {
        $admin = User::factory()->create(['username' => 'admin-finance']);

        return ['Authorization' => 'Bearer '.auth('api')->login($admin)];
    }

    public function test_wallet_payment_records_before_after_and_releases_to_seller_on_completion(): void
    {
        $buyer = Customer::factory()->create();
        $seller = Customer::factory()->create();
        CustomerWallet::create(['customer_id' => $buyer->id, 'available_balance' => 1000000]);
        CustomerWallet::create(['customer_id' => $seller->id]);
        $listing = Product::create(['code' => 'FIN-001', 'name' => 'Tài khoản tài chính', 'product_type' => 'game_account', 'game_code' => 'ninja_school', 'owner_customer_id' => $seller->id, 'status' => 'active', 'approval_status' => 'approved', 'is_published' => true, 'availability_status' => 'available', 'sale_price' => 500000, 'published_at' => now()]);
        $transaction = $this->postJson('/api/v1/customer/products/'.$listing->id.'/transact', ['payment_method' => 'wallet'], $this->customerHeaders($buyer))->assertCreated()->json('data');
        $payment = $transaction['payments'][0];
        $this->postJson('/api/v1/customer/transactions/'.$transaction['id'].'/payments/'.$payment['id'].'/submit', ['payment_method' => 'wallet'], $this->customerHeaders($buyer))->assertOk()->assertJsonPath('data.status', 'confirmed');
        $this->assertDatabaseHas('customer_wallets', ['customer_id' => $buyer->id, 'available_balance' => 500000]);
        $this->assertDatabaseHas('customer_wallets', ['customer_id' => $seller->id, 'held_balance' => 500000]);
        $this->assertDatabaseHas('wallet_transactions', ['customer_id' => $buyer->id, 'type' => 'transaction_payment', 'available_before' => 1000000, 'available_after' => 500000]);
        $this->postJson('/api/v1/customer/transactions/'.$transaction['id'].'/actions', ['action' => 'seller_handover'], $this->customerHeaders($seller))->assertOk();
        $this->postJson('/api/v1/customer/transactions/'.$transaction['id'].'/actions', ['action' => 'buyer_receive'], $this->customerHeaders($buyer))->assertOk();
        $this->postJson('/api/v1/customer/transactions/'.$transaction['id'].'/actions', ['action' => 'complete'], $this->customerHeaders($buyer))->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertDatabaseHas('customer_wallets', ['customer_id' => $seller->id, 'available_balance' => 500000, 'held_balance' => 0]);
        $this->assertDatabaseHas('transaction_payments', ['id' => $payment['id'], 'settlement_status' => 'released']);
    }

    public function test_rental_rate_creates_deposit_and_periodic_cycles_then_refunds_deposit(): void
    {
        $renter = Customer::factory()->create();
        $lessor = Customer::factory()->create();
        CustomerWallet::create(['customer_id' => $renter->id, 'available_balance' => 2000000]);
        CustomerWallet::create(['customer_id' => $lessor->id]);
        $listing = Product::create(['code' => 'RENT-001', 'name' => 'Tài khoản cho thuê', 'product_type' => 'game_account', 'game_code' => 'ninja_school', 'owner_customer_id' => $lessor->id, 'status' => 'active', 'approval_status' => 'approved', 'is_published' => true, 'availability_status' => 'available', 'rental_price' => 300000, 'rental_price_unit' => 'day', 'rental_period_unit' => 'day', 'minimum_rental_period' => 1, 'rental_billing_mode' => 'periodic', 'rental_billing_cycle_unit' => 'day', 'rental_billing_cycle_count' => 1, 'rental_deposit_amount' => 400000, 'published_at' => now()]);
        $rate = $listing->rentalRates()->create(['label' => '3 ngày', 'period_unit' => 'day', 'period_count' => 3, 'price' => 750000, 'deposit_amount' => 400000, 'is_default' => true, 'is_active' => true]);
        $transaction = $this->postJson('/api/v1/customer/products/'.$listing->id.'/transact', ['rental_rate_id' => $rate->id, 'payment_method' => 'wallet', 'rental_start_at' => now()->toISOString()], $this->customerHeaders($renter))->assertCreated()->assertJsonCount(4, 'data.payments')->json('data');
        $this->assertEquals('1150000.00', $transaction['total_payable']);
        foreach ($transaction['payments'] as $payment) {
            $this->postJson('/api/v1/customer/transactions/'.$transaction['id'].'/payments/'.$payment['id'].'/submit', ['payment_method' => 'wallet'], $this->customerHeaders($renter))->assertOk();
        }
        $this->postJson('/api/v1/customer/transactions/'.$transaction['id'].'/actions', ['action' => 'seller_handover'], $this->customerHeaders($lessor))->assertOk();
        $this->postJson('/api/v1/customer/transactions/'.$transaction['id'].'/actions', ['action' => 'buyer_receive'], $this->customerHeaders($renter))->assertOk()->assertJsonPath('data.status', 'active');
        $this->postJson('/api/v1/customer/transactions/'.$transaction['id'].'/actions', ['action' => 'renter_return'], $this->customerHeaders($renter))->assertOk();
        $this->postJson('/api/v1/customer/transactions/'.$transaction['id'].'/actions', ['action' => 'lessor_receive_return'], $this->customerHeaders($lessor))->assertOk();
        $this->postJson('/api/v1/customer/transactions/'.$transaction['id'].'/actions', ['action' => 'complete'], $this->customerHeaders($renter))->assertOk();
        $this->assertDatabaseHas('customer_wallets', ['customer_id' => $renter->id, 'available_balance' => 1250000]);
        $this->assertDatabaseHas('customer_wallets', ['customer_id' => $lessor->id, 'available_balance' => 750000, 'held_balance' => 0]);
        $this->assertDatabaseHas('transaction_payments', ['transaction_id' => $transaction['id'], 'component_type' => 'security_deposit', 'settlement_status' => 'refunded']);
        $this->assertDatabaseHas('transaction_payments', ['transaction_id' => $transaction['id'], 'component_type' => 'rental_fee', 'settlement_status' => 'released']);
    }

    public function test_admin_confirms_deposit_and_wallet_history_keeps_before_after_balances(): void
    {
        Storage::fake('public');
        $customer = Customer::factory()->create();
        CustomerWallet::create(['customer_id' => $customer->id, 'available_balance' => 100000]);

        $deposit = $this->postJson('/api/v1/customer/wallet/deposit/bank', [
            'amount' => 300000,
            'payment_method' => 'bank',
        ], $this->customerHeaders($customer))->assertCreated()->assertJsonPath('data.status', 'draft')->json('data');

        $this->post('/api/v1/customer/wallet/deposits/'.$deposit['id'].'/proof', [
            'proof' => UploadedFile::fake()->image('bank-proof.jpg'),
            'external_reference' => 'BANK-001',
        ], $this->customerHeaders($customer))->assertOk()->assertJsonPath('data.status', 'submitted');

        $this->postJson('/api/v1/wallet-deposits/'.$deposit['id'].'/confirm', [], $this->adminHeaders())->assertOk()->assertJsonPath('data.status', 'confirmed');
        $this->assertDatabaseHas('customer_wallets', ['customer_id' => $customer->id, 'available_balance' => 400000]);
        $this->assertDatabaseHas('wallet_transactions', [
            'customer_id' => $customer->id,
            'type' => 'deposit_confirmed',
            'available_before' => 100000,
            'available_after' => 400000,
        ]);
    }
}
