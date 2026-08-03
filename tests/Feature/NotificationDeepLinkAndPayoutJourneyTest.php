<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPayoutAccount;
use App\Models\CustomerVerification;
use App\Models\CustomerWallet;
use App\Models\MarketplaceNotification;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationDeepLinkAndPayoutJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_notification_detail_contains_transaction_deep_link_and_next_action(): void
    {
        $buyer = Customer::factory()->create();
        $seller = Customer::factory()->create();
        $product = Product::query()->create([
            'code' => 'NTF-PRODUCT',
            'name' => 'Sản phẩm thông báo',
            'product_type' => 'game_account',
            'game_code' => 'ninja_school',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_published' => true,
            'availability_status' => 'available',
            'owner_customer_id' => $seller->id,
            'sale_price' => 100000,
        ]);
        $transaction = Transaction::query()->create([
            'code' => 'NTF-TRANSACTION',
            'product_id' => $product->id,
            'buyer_customer_id' => $buyer->id,
            'seller_customer_id' => $seller->id,
            'transaction_type' => 'purchase',
            'purchase_mode' => 'full',
            'transaction_value' => 100000,
            'total_payable' => 100000,
            'paid_amount' => 0,
            'transaction_date' => now()->toDateString(),
            'status' => 'pending_payment',
        ]);
        $notification = MarketplaceNotification::query()->create([
            'customer_id' => $buyer->id,
            'transaction_id' => $transaction->id,
            'transaction_code' => $transaction->code,
            'type' => 'payment_due',
            'title' => 'Thanh toán giao dịch',
            'message' => 'Giao dịch đang chờ thanh toán.',
        ]);

        $this->getJson('/api/v1/notifications/'.$notification->id, $this->adminHeaders())
            ->assertOk()
            ->assertJsonPath('data.action_context.deep_link', '/transactions/'.$transaction->id)
            ->assertJsonPath('data.action_context.transaction_status.value', 'pending_payment');
    }

    public function test_customer_payout_overview_contains_next_action_and_blocking_reasons(): void
    {
        $customer = Customer::factory()->create();
        CustomerWallet::query()->create([
            'customer_id' => $customer->id,
            'available_balance' => 100000,
            'held_balance' => 0,
        ]);
        CustomerVerification::query()->create([
            'customer_id' => $customer->id,
            'status' => 'unverified',
        ]);

        $this->actingAs($customer, 'customer_api')
            ->getJson('/api/v1/customer/payouts')
            ->assertOk()
            ->assertJsonPath('data.journey.next_action.key', 'submit_verification')
            ->assertJsonPath('data.journey.can_withdraw', false);
    }

    public function test_admin_withdrawal_list_contains_journey_context(): void
    {
        $customer = Customer::factory()->create();
        CustomerVerification::query()->create([
            'customer_id' => $customer->id,
            'status' => 'verified',
        ]);
        CustomerWallet::query()->create([
            'customer_id' => $customer->id,
            'available_balance' => 500000,
            'held_balance' => 100000,
        ]);
        $account = CustomerPayoutAccount::query()->create([
            'customer_id' => $customer->id,
            'bank_code' => 'VCB',
            'bank_name' => 'Vietcombank',
            'account_name' => 'TEST CUSTOMER',
            'account_number' => '123456789',
            'status' => 'verified',
        ]);
        WithdrawalRequest::query()->create([
            'code' => 'RUT-TEST',
            'idempotency_key' => 'RUT-TEST',
            'customer_id' => $customer->id,
            'payout_account_id' => $account->id,
            'amount' => 100000,
            'fee_amount' => 0,
            'net_amount' => 100000,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->getJson('/api/v1/withdrawals', $this->adminHeaders())
            ->assertOk()
            ->assertJsonPath('data.data.0.journey.next_action.key', 'approve')
            ->assertJsonPath('data.data.0.journey.customer_context.verification_status', 'verified');
    }

    private function adminHeaders(): array
    {
        return ['Authorization' => 'Bearer '.auth('api')->login(User::factory()->create())];
    }
}
