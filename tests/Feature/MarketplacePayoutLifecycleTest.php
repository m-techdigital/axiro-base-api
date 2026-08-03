<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPayoutAccount;
use App\Models\CustomerVerification;
use App\Models\CustomerWallet;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\Payouts\WithdrawalService;
use App\Services\Wallet\WalletLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MarketplacePayoutLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_seller_can_reserve_and_admin_can_reject_with_balance_restored(): void
    {
        $customer = Customer::create(['code' => 'CUS-001', 'username' => 'seller01', 'name' => 'Seller', 'email' => 'seller@example.com', 'phone' => '0900000000', 'password' => 'password', 'status' => 'active']);
        CustomerVerification::create(['customer_id' => $customer->id, 'status' => 'verified', 'document_type' => 'citizen_id', 'document_number' => '001']);
        $account = CustomerPayoutAccount::create(['customer_id' => $customer->id, 'bank_code' => 'VCB', 'bank_name' => 'Vietcombank', 'account_name' => 'SELLER', 'account_number' => '123456789', 'status' => 'verified', 'is_default' => true]);
        app(WalletLedgerService::class)->creditAvailable($customer->id, '1000000', 'test_credit', ['idempotency_key' => 'test-credit']);
        $admin = User::factory()->create();
        $service = app(WithdrawalService::class);
        $withdrawal = $service->submit($customer->id, $account->id, '300000', 'test', 'wd-test');
        $wallet = CustomerWallet::where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame('700000.00', (string) $wallet->available_balance);
        $this->assertSame('300000.00', (string) $wallet->held_balance);
        $service->reject($withdrawal, $admin->id, 'Không duyệt');
        $wallet->refresh();
        $this->assertSame('1000000.00', (string) $wallet->available_balance);
        $this->assertSame('0.00', (string) $wallet->held_balance);
        $this->assertSame('rejected', WithdrawalRequest::findOrFail($withdrawal->id)->status);
    }

    public function test_unverified_seller_cannot_withdraw(): void
    {
        $customer = Customer::create(['code' => 'CUS-002', 'username' => 'seller02', 'name' => 'Seller 2', 'email' => 'seller2@example.com', 'phone' => '0900000001', 'password' => 'password', 'status' => 'active']);
        $account = CustomerPayoutAccount::create(['customer_id' => $customer->id, 'bank_code' => 'VCB', 'bank_name' => 'Vietcombank', 'account_name' => 'SELLER 2', 'account_number' => '987654321', 'status' => 'verified']);
        $this->expectException(ValidationException::class);
        app(WithdrawalService::class)->submit($customer->id, $account->id, '50000');
    }

    public function test_customer_can_cancel_submitted_withdrawal_and_balance_is_restored_idempotently(): void
    {
        $customer = Customer::create(['code' => 'CUS-003', 'username' => 'seller03', 'name' => 'Seller 3', 'email' => 'seller3@example.com', 'phone' => '0900000002', 'password' => 'password', 'status' => 'active']);
        CustomerVerification::create(['customer_id' => $customer->id, 'status' => 'verified', 'document_type' => 'citizen_id', 'document_number' => '003']);
        $account = CustomerPayoutAccount::create(['customer_id' => $customer->id, 'bank_code' => 'VCB', 'bank_name' => 'Vietcombank', 'account_name' => 'SELLER 3', 'account_number' => '1122334455', 'status' => 'verified']);
        app(WalletLedgerService::class)->creditAvailable($customer->id, '500000', 'test_credit', ['idempotency_key' => 'test-credit-cancel']);

        $service = app(WithdrawalService::class);
        $withdrawal = $service->submit($customer->id, $account->id, '200000', null, 'wd-cancel-test');
        $service->cancelByCustomer($withdrawal, $customer->id);
        $service->cancelByCustomer($withdrawal->fresh(), $customer->id);

        $wallet = CustomerWallet::where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame('500000.00', (string) $wallet->available_balance);
        $this->assertSame('0.00', (string) $wallet->held_balance);
        $this->assertSame('cancelled_by_customer', WithdrawalRequest::findOrFail($withdrawal->id)->status);
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'withdrawal_cancelled_by_customer',
            'actor_type' => 'customer',
            'actor_id' => $customer->id,
            'entity_id' => $withdrawal->id,
        ]);
    }

    public function test_customer_cannot_cancel_approved_withdrawal(): void
    {
        $customer = Customer::create(['code' => 'CUS-004', 'username' => 'seller04', 'name' => 'Seller 4', 'email' => 'seller4@example.com', 'phone' => '0900000003', 'password' => 'password', 'status' => 'active']);
        CustomerVerification::create(['customer_id' => $customer->id, 'status' => 'verified', 'document_type' => 'citizen_id', 'document_number' => '004']);
        $account = CustomerPayoutAccount::create(['customer_id' => $customer->id, 'bank_code' => 'ACB', 'bank_name' => 'ACB', 'account_name' => 'SELLER 4', 'account_number' => '5566778899', 'status' => 'verified']);
        app(WalletLedgerService::class)->creditAvailable($customer->id, '500000', 'test_credit', ['idempotency_key' => 'test-credit-approved']);
        $admin = User::factory()->create();
        $service = app(WithdrawalService::class);
        $withdrawal = $service->submit($customer->id, $account->id, '200000', null, 'wd-approved-test');
        $service->approve($withdrawal, $admin->id);

        $this->expectException(ValidationException::class);
        $service->cancelByCustomer($withdrawal->fresh(), $customer->id);
    }
}
