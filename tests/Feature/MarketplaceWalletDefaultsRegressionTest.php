<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Services\Wallet\WalletLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceWalletDefaultsRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_created_with_only_customer_id_has_canonical_zero_balances(): void
    {
        $customer = Customer::factory()->create();
        $wallet = CustomerWallet::create(['customer_id' => $customer->id]);

        $this->assertSame('0.00', $wallet->available_balance);
        $this->assertSame('0.00', $wallet->held_balance);
        $this->assertSame('0.00', $wallet->lifetime_credit);
        $this->assertSame('0.00', $wallet->lifetime_debit);

        app(WalletLedgerService::class)->creditHeld($customer->id, '100000.00', 'regression_hold');

        $this->assertDatabaseHas('customer_wallets', [
            'customer_id' => $customer->id,
            'available_balance' => 0,
            'held_balance' => 100000,
            'lifetime_credit' => 100000,
            'lifetime_debit' => 0,
        ]);
    }

    public function test_release_and_transfer_use_distinct_idempotency_entries(): void
    {
        $seller = Customer::factory()->create();
        $buyer = Customer::factory()->create();
        CustomerWallet::create(['customer_id' => $seller->id]);
        CustomerWallet::create(['customer_id' => $buyer->id]);

        $ledger = app(WalletLedgerService::class);
        $ledger->creditHeld($seller->id, '500000.00', 'regression_hold', ['idempotency_key' => 'hold:release']);
        $ledger->releaseHeld($seller->id, '200000.00', ['idempotency_key' => 'release:test']);
        $ledger->transferHeldToAvailable($seller->id, $buyer->id, '100000.00', 'refund_test', ['idempotency_key' => 'transfer:test']);

        $this->assertDatabaseHas('customer_wallets', [
            'customer_id' => $seller->id,
            'available_balance' => 200000,
            'held_balance' => 200000,
        ]);
        $this->assertDatabaseHas('customer_wallets', [
            'customer_id' => $buyer->id,
            'available_balance' => 100000,
            'held_balance' => 0,
        ]);
        $this->assertDatabaseHas('wallet_transactions', ['idempotency_key' => 'release:test:held']);
        $this->assertDatabaseHas('wallet_transactions', ['idempotency_key' => 'release:test:available']);
        $this->assertDatabaseHas('wallet_transactions', ['idempotency_key' => 'transfer:test:debit']);
        $this->assertDatabaseHas('wallet_transactions', ['idempotency_key' => 'transfer:test:credit']);
    }
}
