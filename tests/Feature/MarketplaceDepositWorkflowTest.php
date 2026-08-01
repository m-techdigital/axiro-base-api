<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MarketplaceDepositWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function customerHeaders(Customer $c): array
    {
        return ['Authorization' => 'Bearer '.auth('customer_api')->login($c)];
    }

    private function adminHeaders(): array
    {
        $u = User::factory()->create(['username' => 'admin', 'password' => 'password']);

        return ['Authorization' => 'Bearer '.auth('api')->login($u)];
    }

    public function test_customer_creates_deposit_uploads_proof_and_admin_confirms(): void
    {
        Storage::fake('public');
        $c = Customer::factory()->create();
        $deposit = $this->postJson('/api/v1/customer/wallet/deposit/bank', ['amount' => 200000, 'payment_method' => 'bank'], $this->customerHeaders($c))->assertCreated()->assertJsonPath('data.status', 'draft')->json('data');
        $this->post('/api/v1/customer/wallet/deposits/'.$deposit['id'].'/proof', ['proof' => UploadedFile::fake()->image('proof.jpg'), 'external_reference' => 'BANK-REF-001'], $this->customerHeaders($c))->assertOk()->assertJsonPath('data.status', 'submitted');
        $this->postJson('/api/v1/wallet-deposits/'.$deposit['id'].'/confirm', [], $this->adminHeaders())->assertOk()->assertJsonPath('data.status', 'confirmed');
        $this->assertDatabaseHas('customer_wallets', ['customer_id' => $c->id, 'available_balance' => 200000]);
        $this->assertDatabaseHas('wallet_transactions', ['id' => $deposit['id'], 'status' => 'confirmed']);
    }
}
