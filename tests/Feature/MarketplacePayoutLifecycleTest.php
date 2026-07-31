<?php
namespace Tests\Feature;
use App\Models\{Customer,CustomerPayoutAccount,CustomerVerification,CustomerWallet,User,WithdrawalRequest};
use App\Services\Payouts\WithdrawalService;
use App\Services\Wallet\WalletLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class MarketplacePayoutLifecycleTest extends TestCase {
 use RefreshDatabase;
 public function test_verified_seller_can_reserve_and_admin_can_reject_with_balance_restored(): void {
  $customer=Customer::create(['code'=>'CUS-001','username'=>'seller01','name'=>'Seller','email'=>'seller@example.com','phone'=>'0900000000','password'=>'password','status'=>'active']);
  CustomerVerification::create(['customer_id'=>$customer->id,'status'=>'verified','document_type'=>'citizen_id','document_number'=>'001']);
  $account=CustomerPayoutAccount::create(['customer_id'=>$customer->id,'bank_code'=>'VCB','bank_name'=>'Vietcombank','account_name'=>'SELLER','account_number'=>'123456789','status'=>'verified','is_default'=>true]);
  app(WalletLedgerService::class)->creditAvailable($customer->id,'1000000','test_credit',['idempotency_key'=>'test-credit']);
  $admin=User::factory()->create();
  $service=app(WithdrawalService::class);
  $withdrawal=$service->submit($customer->id,$account->id,'300000','test','wd-test');
  $wallet=CustomerWallet::where('customer_id',$customer->id)->firstOrFail();
  $this->assertSame('700000.00',(string)$wallet->available_balance);
  $this->assertSame('300000.00',(string)$wallet->held_balance);
  $service->reject($withdrawal,$admin->id,'Không duyệt');
  $wallet->refresh();
  $this->assertSame('1000000.00',(string)$wallet->available_balance);
  $this->assertSame('0.00',(string)$wallet->held_balance);
  $this->assertSame('rejected',WithdrawalRequest::findOrFail($withdrawal->id)->status);
 }
 public function test_unverified_seller_cannot_withdraw(): void {
  $customer=Customer::create(['code'=>'CUS-002','username'=>'seller02','name'=>'Seller 2','email'=>'seller2@example.com','phone'=>'0900000001','password'=>'password','status'=>'active']);
  $account=CustomerPayoutAccount::create(['customer_id'=>$customer->id,'bank_code'=>'VCB','bank_name'=>'Vietcombank','account_name'=>'SELLER 2','account_number'=>'987654321','status'=>'verified']);
  $this->expectException(\Illuminate\Validation\ValidationException::class);
  app(WithdrawalService::class)->submit($customer->id,$account->id,'50000');
 }
}
