<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerWalletVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_wallet_ledger_exposes_only_customer_relevant_fields(): void
    {
        $customer = Customer::factory()->create();
        CustomerWallet::create([
            'customer_id' => $customer->id,
            'available_balance' => 150000,
            'held_balance' => 50000,
            'lifetime_credit' => 900000,
            'lifetime_debit' => 700000,
        ]);

        WalletTransaction::create([
            'code' => 'WAL-INTERNAL-001',
            'idempotency_key' => 'secret-idempotency-key',
            'customer_id' => $customer->id,
            'type' => 'deposit_confirmed',
            'direction' => 'credit',
            'balance_bucket' => 'available',
            'amount' => 150000,
            'available_before' => 0,
            'available_after' => 150000,
            'held_before' => 0,
            'held_after' => 0,
            'balance_after' => 150000,
            'status' => 'confirmed',
            'external_reference' => 'BANK-PRIVATE-REFERENCE',
            'metadata' => ['internal' => 'secret'],
            'occurred_at' => now(),
        ]);

        $headers = ['Authorization' => 'Bearer '.auth('customer_api')->login($customer)];

        $response = $this->getJson('/api/v1/customer/wallet/transactions', $headers)
            ->assertOk()
            ->assertJsonPath('data.wallet.available_balance', '150000.00')
            ->assertJsonPath('data.wallet.held_balance', '50000.00')
            ->assertJsonPath('data.wallet.total_balance', '200000.00')
            ->assertJsonMissingPath('data.wallet.lifetime_credit')
            ->assertJsonMissingPath('data.wallet.lifetime_debit');

        foreach (['code', 'idempotency_key', 'customer_id', 'external_reference', 'metadata', 'available_before', 'held_before'] as $field) {
            $response->assertJsonMissingPath('data.transactions.data.0.'.$field);
        }

        $response
            ->assertJsonPath('data.transactions.data.0.type', 'deposit_confirmed')
            ->assertJsonPath('data.transactions.data.0.balance_after', '150000.00');
    }
}
