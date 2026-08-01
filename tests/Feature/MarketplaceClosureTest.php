<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MarketplaceDispute;
use App\Models\MarketplaceFeePolicy;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionAssetSnapshot;
use App\Services\Marketplace\MarketplaceFeeCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_fee_policy_is_snapshotted_without_mutating_transaction_value(): void
    {
        MarketplaceFeePolicy::create(['code' => 'PURCHASE-5', 'name' => 'Phí mua bán', 'transaction_type' => 'purchase', 'buyer_fee_rate' => 2, 'buyer_fixed_fee' => 1000, 'seller_fee_rate' => 3, 'seller_fixed_fee' => 2000, 'tax_rate' => 10, 'priority' => 1, 'is_active' => true]);
        $fee = app(MarketplaceFeeCalculator::class)->calculate('purchase', '100000.00');
        $this->assertSame('3000.00', $fee['buyer_fee_amount']);
        $this->assertSame('5000.00', $fee['seller_fee_amount']);
        $this->assertSame('800.00', $fee['tax_amount']);
        $this->assertSame('95000.00', $fee['seller_net_amount']);
        $this->assertStringStartsWith('PURCHASE-5@', $fee['fee_policy_version']);
    }

    public function test_case_and_asset_snapshot_share_transaction_owner(): void
    {
        $buyer = Customer::create(['code' => 'CUS-B', 'username' => 'buyer', 'name' => 'Buyer', 'email' => 'buyer@example.com', 'phone' => '0900000001', 'password' => 'password', 'status' => 'active']);
        $seller = Customer::create(['code' => 'CUS-S', 'username' => 'seller', 'name' => 'Seller', 'email' => 'seller@example.com', 'phone' => '0900000002', 'password' => 'password', 'status' => 'active']);
        $product = Product::create(['code' => 'PRO-1', 'name' => 'Nick test', 'product_type' => 'game_account', 'game_code' => 'ninja_school', 'owner_customer_id' => $seller->id, 'approval_status' => 'approved', 'is_published' => true, 'status' => 'active', 'availability_status' => 'available', 'rental_price' => 100000, 'rental_deposit_amount' => 50000, 'published_at' => now()]);
        $transaction = Transaction::create(['code' => 'TRX-1', 'transaction_type' => 'rental', 'purchase_mode' => 'rental',
            'product_id' => $product->id, 'buyer_customer_id' => $buyer->id, 'seller_customer_id' => $seller->id, 'transaction_value' => 100000, 'total_payable' => 150000, 'seller_net_amount' => 100000, 'transaction_date' => now()->toDateString(), 'status' => 'paid']);
        $case = MarketplaceDispute::create(['code' => 'CASE-1', 'transaction_id' => $transaction->id, 'opened_by_customer_id' => $buyer->id, 'case_type' => 'return_issue', 'reason' => 'Sai hiện trạng', 'description' => 'Cần kiểm tra', 'status' => 'open']);
        $snapshot = TransactionAssetSnapshot::create(['transaction_id' => $transaction->id, 'stage' => 'before_return', 'customer_id' => $buyer->id, 'actor_type' => 'customer', 'actor_id' => $buyer->id, 'images' => ['https://example.com/a.jpg'], 'captured_at' => now()]);
        $this->assertSame($transaction->id, $case->transaction_id);
        $this->assertSame($transaction->id, $snapshot->transaction_id);
    }
}
