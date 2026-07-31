<?php

namespace Tests\Feature;

use App\Models\{Customer,MarketplaceDispute,Product,ProductListing,Transaction,WalletTransaction};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerSupportAndWalletTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_ledger_exposes_before_after_and_pending_deposit_summary(): void
    {
        $customer = Customer::factory()->create(['password' => Hash::make('password')]);
        $customer->wallet()->create(['available_balance' => '100000.00', 'held_balance' => '20000.00']);

        WalletTransaction::create([
            'code' => 'NAP-PENDING',
            'idempotency_key' => 'nap-pending',
            'customer_id' => $customer->id,
            'type' => 'deposit_request',
            'direction' => 'credit',
            'balance_bucket' => 'available',
            'amount' => '50000.00',
            'available_before' => '100000.00',
            'available_after' => '100000.00',
            'held_before' => '20000.00',
            'held_after' => '20000.00',
            'balance_after' => '100000.00',
            'status' => 'submitted',
            'occurred_at' => now()->subMinute(),
        ]);

        WalletTransaction::create([
            'code' => 'WAL-CONFIRMED',
            'idempotency_key' => 'wallet-confirmed',
            'customer_id' => $customer->id,
            'type' => 'manual_adjustment',
            'direction' => 'credit',
            'balance_bucket' => 'available',
            'amount' => '10000.00',
            'available_before' => '100000.00',
            'available_after' => '110000.00',
            'held_before' => '20000.00',
            'held_after' => '20000.00',
            'balance_after' => '110000.00',
            'status' => 'confirmed',
            'occurred_at' => now(),
        ]);

        $token = auth('customer_api')->login($customer);
        $this->withToken($token)->getJson('/api/v1/customer/wallet/transactions')
            ->assertOk()
            ->assertJsonPath('data.wallet.pending_deposit_balance', 50000)
            ->assertJsonPath('data.transactions.data.0.balance_before', '100000.00')
            ->assertJsonPath('data.transactions.data.0.balance_after', '110000.00');
    }

    public function test_customer_can_open_own_case_detail_but_not_another_customers_case(): void
    {
        $buyer = Customer::factory()->create();
        $seller = Customer::factory()->create();
        $other = Customer::factory()->create();

        $product = Product::create([
            'code' => 'PRD-CASE-TEST',
            'name' => 'Tài khoản kiểm thử hỗ trợ',
            'product_type' => 'game_account',
            'game_code' => 'ninja_school',
            'status' => 'active',
            'price' => '100000.00',
            'owner_customer_id' => $seller->id,
        ]);
        $listing = ProductListing::create([
            'code' => 'LST-CASE-TEST',
            'product_id' => $product->id,
            'owner_customer_id' => $seller->id,
            'listing_type' => 'sale',
            'status' => 'published',
            'title' => 'Tin đăng kiểm thử hỗ trợ',
            'sale_price' => '100000.00',
            'published_at' => now(),
        ]);
        $transaction = Transaction::create([
            'code' => 'TRX-CASE-TEST',
            'transaction_type' => 'purchase',
            'purchase_mode' => 'full',
            'listing_id' => $listing->id,
            'product_id' => $product->id,
            'buyer_customer_id' => $buyer->id,
            'seller_customer_id' => $seller->id,
            'transaction_value' => '100000.00',
            'total_payable' => '100000.00',
            'seller_net_amount' => '100000.00',
            'transaction_date' => now()->toDateString(),
            'status' => 'pending_payment',
        ]);
        $case = MarketplaceDispute::create([
            'code' => 'CASE-TEST',
            'transaction_id' => $transaction->id,
            'opened_by_customer_id' => $buyer->id,
            'case_type' => 'support',
            'reason' => 'Cần hỗ trợ',
            'description' => 'Mô tả',
            'priority' => 'normal',
            'status' => 'open',
        ]);

        $this->withToken(auth('customer_api')->login($buyer))
            ->getJson('/api/v1/customer/cases/'.$case->id)
            ->assertOk()
            ->assertJsonPath('data.code', 'CASE-TEST');
        $this->withToken(auth('customer_api')->login($other))
            ->getJson('/api/v1/customer/cases/'.$case->id)
            ->assertForbidden();
    }
}
