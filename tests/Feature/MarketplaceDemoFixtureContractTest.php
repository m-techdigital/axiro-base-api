<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPayoutAccount;
use App\Models\CustomerVerification;
use App\Models\CustomerWallet;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Database\Seeders\MarketplaceDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceDemoFixtureContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_fixture_is_stable_for_browser_and_transactional_e2e(): void
    {
        User::factory()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'change-me',
        ]);

        $this->seed(MarketplaceDemoSeeder::class);

        foreach (['customer', 'seller', 'renter', 'lessor', 'dispute'] as $username) {
            $customer = Customer::query()->where('username', $username)->firstOrFail();
            $this->assertSame('active', $customer->status);
            $this->assertNotNull(CustomerWallet::query()->where('customer_id', $customer->id)->first());
            $this->assertSame('verified', CustomerVerification::query()->where('customer_id', $customer->id)->value('status'));
            $this->assertSame('verified', CustomerPayoutAccount::query()->where('customer_id', $customer->id)->where('is_default', true)->value('status'));
        }

        $this->assertDatabaseHas('products', [
            'code' => 'NSO-0102',
            'approval_status' => 'approved',
            'is_published' => true,
            'availability_status' => 'available',
        ]);
        $this->assertDatabaseHas('products', [
            'code' => 'NSO-0201',
            'approval_status' => 'approved',
            'is_published' => true,
            'availability_status' => 'available',
        ]);
        $this->assertDatabaseHas('products', [
            'code' => 'NRO-0301',
            'approval_status' => 'approved',
            'is_published' => true,
            'installment_enabled' => true,
            'availability_status' => 'available',
        ]);

        foreach ([
            'TRX-DEMO-INSTALLMENT',
            'TRX-DEMO-COMPLETED-SALE',
            'TRX-DEMO-ACTIVE-RENTAL',
            'TRX-DEMO-RETURNED-RENTAL',
            'TRX-DEMO-DISPUTE-OPEN',
            'TRX-DEMO-CANCELLED',
        ] as $code) {
            $this->assertTrue(Transaction::query()->where('code', $code)->exists(), $code);
        }

        $this->assertSame('submitted', WithdrawalRequest::query()->where('idempotency_key', 'demo-withdrawal-submitted')->value('status'));
        $this->assertSame('paid', WithdrawalRequest::query()->where('idempotency_key', 'demo-withdrawal-paid')->value('status'));
        $this->assertSame(3, Product::query()->whereIn('code', ['NSO-0102', 'NSO-0201', 'NRO-0301'])->count());
    }
}
