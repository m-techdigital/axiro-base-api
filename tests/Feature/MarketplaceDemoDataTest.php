<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MarketplaceDispute;
use App\Models\MarketplaceNotification;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionCheckpoint;
use App\Models\TransactionPayment;
use App\Models\WalletTransaction;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceDemoDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketplace_demo_scenarios_are_complete_and_consistent(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('customers', ['username' => 'customer', 'status' => 'active']);
        $this->assertDatabaseHas('customers', ['username' => 'seller', 'status' => 'active']);
        $this->assertDatabaseHas('customers', ['username' => 'renter', 'status' => 'active']);
        $this->assertDatabaseHas('customers', ['username' => 'lessor', 'status' => 'active']);
        $this->assertDatabaseHas('customers', ['username' => 'dispute', 'status' => 'active']);

        $this->assertSame(2, Product::query()->where('approval_status', 'approved')->where('is_published', true)->count());
        $this->assertDatabaseHas('products', ['code' => 'AVA-0701', 'approval_status' => 'pending', 'is_published' => false]);
        $this->assertDatabaseHas('products', ['code' => 'NSO-0801', 'approval_status' => 'rejected', 'is_published' => false]);

        $installment = Transaction::query()->where('code', 'TRX-DEMO-INSTALLMENT')->firstOrFail();
        $this->assertSame('partially_paid', $installment->status);
        $this->assertSame(3, $installment->payments()->count());
        $this->assertSame(1, $installment->payments()->where('status', 'confirmed')->count());
        $this->assertSame(1, $installment->payments()->where('status', 'submitted')->count());
        $this->assertSame(1, $installment->payments()->where('status', 'pending')->count());

        $this->assertDatabaseHas('transactions', ['code' => 'TRX-DEMO-COMPLETED-SALE', 'status' => 'completed']);
        $this->assertDatabaseHas('transactions', ['code' => 'TRX-DEMO-ACTIVE-RENTAL', 'status' => 'active']);
        $this->assertDatabaseHas('transactions', ['code' => 'TRX-DEMO-RETURNED-RENTAL', 'status' => 'returned']);
        $this->assertDatabaseHas('transactions', ['code' => 'TRX-DEMO-DISPUTE-OPEN', 'status' => 'disputed']);
        $this->assertDatabaseHas('transactions', ['code' => 'TRX-DEMO-CANCELLED', 'status' => 'cancelled']);
        $this->assertSame(
            0,
            Transaction::query()
                ->whereRaw('ABS(COALESCE(seller_net_amount, 0) - CASE WHEN (COALESCE(transaction_value, 0) - COALESCE(seller_fee_amount, 0)) < 0 THEN 0 ELSE (COALESCE(transaction_value, 0) - COALESCE(seller_fee_amount, 0)) END) > 0.01')
                ->count(),
        );
        $this->artisan('marketplace:integrity')->assertExitCode(0);


        $this->assertDatabaseHas('marketplace_disputes', ['code' => 'DSP-DEMO-OPEN', 'status' => 'open']);
        $this->assertDatabaseHas('marketplace_disputes', ['code' => 'DSP-DEMO-RESOLVED', 'status' => 'resolved']);

        $this->assertDatabaseHas('wallet_transactions', ['code' => 'WAL-DEMO-001', 'status' => 'confirmed']);
        $this->assertDatabaseHas('wallet_transactions', ['code' => 'NAP-DEMO-PENDING', 'type' => 'deposit_request', 'status' => 'submitted']);
        $this->assertDatabaseHas('wallet_transactions', ['code' => 'NAP-DEMO-REJECTED', 'type' => 'deposit_request', 'status' => 'rejected']);

        $this->assertGreaterThanOrEqual(1, MarketplaceDispute::query()->where('status', 'open')->count());
        $this->assertGreaterThanOrEqual(7, TransactionPayment::query()->count());
        $this->assertGreaterThanOrEqual(3, WalletTransaction::query()->count());
        $this->assertGreaterThanOrEqual(4, TransactionCheckpoint::query()->count());
        $this->assertGreaterThanOrEqual(5, MarketplaceNotification::query()->count());
        $this->assertSame(5, Customer::query()->whereIn('username', ['customer', 'seller', 'renter', 'lessor', 'dispute'])->count());
    }
}
