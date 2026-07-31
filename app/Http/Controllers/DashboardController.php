<?php
namespace App\Http\Controllers;
use App\Models\{ContentEntry,Contract,Customer,MarketplaceDispute,MarketplacePlatformLedgerEntry,MarketplaceReview,MarketplaceRiskFlag,Product,ProductListing,Transaction,TransactionPayment,WalletTransaction,WithdrawalRequest};
class DashboardController extends Controller {
 public function __invoke(){return success_response([
  'customers'=>Customer::count(),'products'=>Product::count(),'listings'=>ProductListing::count(),'pending_listings'=>ProductListing::where('status','pending_review')->count(),
  'transactions'=>Transaction::count(),'transaction_value'=>(string)Transaction::sum('total_payable'),'seller_net_value'=>(string)Transaction::sum('seller_net_amount'),'platform_fee_revenue'=>(string)MarketplacePlatformLedgerEntry::sum('amount'),
  'pending_payments'=>TransactionPayment::where('status','submitted')->count(),'pending_deposits'=>WalletTransaction::where('status','pending')->count(),'pending_withdrawals'=>WithdrawalRequest::whereIn('status',['submitted','approved'])->count(),
  'contracts'=>Contract::count(),'open_cases'=>MarketplaceDispute::whereNotIn('status',['resolved','rejected','cancelled'])->count(),'open_disputes'=>MarketplaceDispute::where('case_type','dispute')->whereNotIn('status',['resolved','rejected','cancelled'])->count(),
  'open_risk_flags'=>MarketplaceRiskFlag::whereIn('status',['open','reviewing'])->count(),'average_rating'=>(float)(MarketplaceReview::where('status','published')->avg('rating')??0),'published_content'=>ContentEntry::where('status','published')->count(),
  'recent_transactions'=>Transaction::with('product')->latest()->limit(5)->get(),'recent_contracts'=>Contract::with('transaction.product')->latest()->limit(5)->get(),
 ]);}
}
