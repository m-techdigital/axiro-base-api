<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\GeneratedDocument;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use Database\Seeders\MarketplaceDemoSeeder;
use Database\Seeders\MarketplaceDocumentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MiniCustomerIsolationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_wallet_and_documents_never_expose_another_customers_data(): void
    {
        User::factory()->create(['username' => 'admin', 'password' => 'change-me']);
        $this->seed(MarketplaceDemoSeeder::class);
        $this->seed(MarketplaceDocumentSeeder::class);

        $transaction = Transaction::query()
            ->where('code', 'TRX-DEMO-COMPLETED-SALE')
            ->firstOrFail();
        $buyer = Customer::query()->findOrFail($transaction->buyer_customer_id);
        $outsider = Customer::factory()->create();
        $outsider->wallet()->create([
            'available_balance' => '50000.00',
            'held_balance' => '0.00',
        ]);

        WalletTransaction::query()->create([
            'code' => 'WAL-OUTSIDER-ONLY',
            'idempotency_key' => 'wallet-outsider-only',
            'customer_id' => $outsider->id,
            'type' => 'manual_adjustment',
            'direction' => 'credit',
            'balance_bucket' => 'available',
            'amount' => '50000.00',
            'available_before' => '0.00',
            'available_after' => '50000.00',
            'held_before' => '0.00',
            'held_after' => '0.00',
            'balance_after' => '50000.00',
            'status' => 'confirmed',
            'occurred_at' => now(),
        ]);

        $document = GeneratedDocument::query()
            ->where('transaction_id', $transaction->id)
            ->firstOrFail();

        $buyerToken = auth('customer_api')->login($buyer);
        $this->withToken($buyerToken)
            ->getJson('/api/v1/customer/wallet/transactions')
            ->assertOk()
            ->assertJsonMissing(['code' => 'WAL-OUTSIDER-ONLY']);

        $outsiderToken = auth('customer_api')->login($outsider);
        $this->withToken($outsiderToken)
            ->getJson('/api/v1/customer/documents')
            ->assertOk()
            ->assertJsonMissing(['id' => $document->id]);

        $this->withToken($outsiderToken)
            ->getJson("/api/v1/customer/documents/{$document->id}/preview")
            ->assertForbidden();

        $this->withToken($outsiderToken)
            ->get("/api/v1/customer/documents/{$document->id}/download")
            ->assertForbidden();

        $privateWithdrawal = WithdrawalRequest::query()
            ->where('idempotency_key', 'demo-withdrawal-submitted')
            ->firstOrFail();

        $this->withToken($outsiderToken)
            ->getJson('/api/v1/customer/payouts')
            ->assertOk()
            ->assertJsonMissing(['id' => $privateWithdrawal->id])
            ->assertJsonMissing(['idempotency_key' => 'demo-withdrawal-submitted']);

        $this->withToken($outsiderToken)
            ->postJson("/api/v1/customer/withdrawals/{$privateWithdrawal->id}/cancel")
            ->assertNotFound();
    }
}
