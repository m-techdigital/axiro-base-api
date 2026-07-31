<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceIntegrityCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_empty_database_passes_integrity_check(): void
    {
        $this->artisan('marketplace:integrity')->assertExitCode(0);
    }

    public function test_negative_wallet_is_detected(): void
    {
        $customer = Customer::factory()->create();
        CustomerWallet::query()->create([
            'customer_id' => $customer->id,
            'available_balance' => -1,
            'held_balance' => 0,
            'lifetime_credit' => 0,
            'lifetime_debit' => 0,
        ]);

        $this->artisan('marketplace:integrity')->assertExitCode(1);
    }
}
