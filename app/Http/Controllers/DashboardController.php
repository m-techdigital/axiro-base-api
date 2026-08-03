<?php

namespace App\Http\Controllers;

use App\Models\ContentEntry;
use App\Models\Customer;
use App\Models\GeneratedDocument;
use App\Models\MarketplaceDispute;
use App\Models\MarketplacePlatformLedgerEntry;
use App\Models\MarketplaceReview;
use App\Models\MarketplaceRiskFlag;
use App\Models\Product;
use App\Models\ProductHold;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;

class DashboardController extends Controller
{
    public function __invoke()
    {
        return success_response([
            'customers' => Customer::count(), 'products' => Product::count(), 'pending_products' => Product::where('approval_status', 'pending')->count(),
            'transactions' => Transaction::count(), 'transaction_value' => (string) Transaction::sum('total_payable'), 'seller_net_value' => (string) Transaction::sum('seller_net_amount'), 'platform_fee_revenue' => (string) MarketplacePlatformLedgerEntry::sum('amount'),
            'pending_payments' => TransactionPayment::where('status', 'submitted')->count(), 'pending_deposits' => WalletTransaction::where('type', 'deposit_request')->whereIn('status', ['pending', 'submitted'])->count(), 'pending_withdrawals' => WithdrawalRequest::whereIn('status', ['submitted', 'approved'])->count(),
            'rental_deposit_review' => Transaction::where('transaction_type', 'rental')->where('status', 'returned')->where('deposit_amount', '>', 0)->count(), 'active_holds' => ProductHold::where('status', 'active')->count(), 'expired_holds' => ProductHold::where('status', 'active')->where('hold_until', '<=', now())->count(), 'generated_documents' => GeneratedDocument::count(),
            'open_cases' => MarketplaceDispute::whereNotIn('status', ['resolved', 'rejected', 'cancelled'])->count(), 'open_disputes' => MarketplaceDispute::where('case_type', 'dispute')->whereNotIn('status', ['resolved', 'rejected', 'cancelled'])->count(),
            'open_risk_flags' => MarketplaceRiskFlag::whereIn('status', ['open', 'reviewing'])->count(), 'average_rating' => (float) (MarketplaceReview::where('status', 'published')->avg('rating') ?? 0), 'published_content' => ContentEntry::where('status', 'published')->count(),
            'recent_transactions' => Transaction::with('product')->latest()->limit(5)->get(),
        ]);
    }
}
