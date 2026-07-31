<?php

namespace Tests\Feature;

use App\Models\{Customer, CustomerWallet, Product, ProductListing, Transaction, WalletTransaction};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerTransactionExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_transaction_does_not_offer_cancel_and_keeps_product_details(): void
    {
        $buyer = Customer::create(['code'=>'CUS-BUYER','username'=>'buyer','name'=>'Buyer','password'=>Hash::make('secret123'),'status'=>'active']);
        $seller = Customer::create(['code'=>'CUS-SELLER','username'=>'seller','name'=>'Seller','password'=>Hash::make('secret123'),'status'=>'active']);
        $product = Product::create(['code'=>'PRD-001','name'=>'Nhân vật Ninja School cấp 110','product_type'=>'game_account','game_code'=>'ninja_school','server_name'=>'Server 1','level'=>'110','status'=>'active','price'=>100000,'attributes'=>['class'=>'Kunai'],'owner_customer_id'=>$seller->id]);
        $listing = ProductListing::create(['code'=>'LST-001','product_id'=>$product->id,'owner_customer_id'=>$seller->id,'listing_type'=>'sale','status'=>'published','title'=>'Bán nhân vật Ninja School','sale_price'=>100000]);
        $transaction = Transaction::create(['code'=>'TRX-001','transaction_type'=>'purchase','purchase_mode'=>'full','listing_id'=>$listing->id,'product_id'=>$product->id,'buyer_customer_id'=>$buyer->id,'seller_customer_id'=>$seller->id,'transaction_value'=>100000,'total_payable'=>100000,'paid_amount'=>0,'transaction_date'=>now()->toDateString(),'status'=>'pending_payment']);

        $this->actingAs($buyer, 'customer_api')->getJson('/api/v1/customer/transactions/'.$transaction->id)
            ->assertOk()
            ->assertJsonPath('data.product.game_code', 'ninja_school')
            ->assertJsonPath('data.product.server_name', 'Server 1')
            ->assertJsonMissing(['cancel']);
    }

    public function test_pending_deposit_is_listed_separately_and_not_in_wallet_ledger(): void
    {
        $customer = Customer::create(['code'=>'CUS-WALLET','username'=>'wallet','name'=>'Wallet Customer','password'=>Hash::make('secret123'),'status'=>'active']);
        CustomerWallet::create(['customer_id'=>$customer->id,'available_balance'=>0,'held_balance'=>0]);
        WalletTransaction::create(['code'=>'NAP-001','idempotency_key'=>'deposit:test','customer_id'=>$customer->id,'type'=>'deposit_request','direction'=>'credit','balance_bucket'=>'available','amount'=>200000,'available_before'=>0,'available_after'=>0,'held_before'=>0,'held_after'=>0,'balance_after'=>0,'status'=>'submitted','payment_method'=>'bank','occurred_at'=>now()]);

        $this->actingAs($customer, 'customer_api')->getJson('/api/v1/customer/wallet/transactions')->assertOk()->assertJsonCount(0, 'data.transactions.data');
        $this->actingAs($customer, 'customer_api')->getJson('/api/v1/customer/wallet/deposits')->assertOk()->assertJsonPath('data.data.0.code', 'NAP-001');
    }
}
