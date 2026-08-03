<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceDispute;
use App\Models\Product;
use App\Models\ProductHold;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;

class AdminActionCenterController extends Controller
{
    public function __invoke()
    {
        return success_response([
            'counts' => [
                'pending_products' => Product::where('approval_status', 'pending')->count(),
                'submitted_payments' => TransactionPayment::where('status', 'submitted')->count(),
                'overdue_payments' => TransactionPayment::where('status', 'overdue')->count(),
                'pending_deposits' => WalletTransaction::where('type', 'deposit_request')->whereIn('status', ['pending', 'submitted'])->count(),
                'open_disputes' => MarketplaceDispute::where('status', 'open')->count(),
                'handover_pending' => Transaction::whereIn('status', ['handover_pending', 'return_pending'])->count(),
                'overdue_rentals' => Transaction::where('transaction_type', 'rental')->where('status', 'overdue')->count(),
                'rental_deposit_review' => Transaction::where('transaction_type', 'rental')->where('status', 'returned')->where('deposit_amount', '>', 0)->count(),
                'pending_payouts' => WithdrawalRequest::whereIn('status', ['submitted', 'approved'])->count(),
                'active_holds' => ProductHold::where('status', 'active')->count(),
                'expired_holds' => ProductHold::where('status', 'active')->where('hold_until', '<=', now())->count(),
            ],
            'products' => Product::with(['owner:id,code,name', 'rentalRates'])->where('status', 'pending_review')->latest()->limit(6)->get(),
            'payments' => TransactionPayment::with(['transaction.product', 'customer:id,code,name'])->whereIn('status', ['submitted', 'overdue'])->latest()->limit(8)->get(),
            'deposits' => WalletTransaction::with('customer:id,code,name')->where('type', 'deposit_request')->whereIn('status', ['pending', 'submitted'])->latest()->limit(6)->get(),
            'disputes' => MarketplaceDispute::with(['transaction.product', 'openedBy:id,code,name'])->where('status', 'open')->latest()->limit(6)->get(),
            'transactions' => Transaction::with(['product', 'buyer:id,code,name', 'seller:id,code,name', 'checkpoints'])->whereIn('status', ['handover_pending', 'return_pending'])->latest()->limit(6)->get(),
            'rental_deposits' => Transaction::with(['product', 'buyer:id,code,name', 'seller:id,code,name'])->where('transaction_type', 'rental')->where('status', 'returned')->where('deposit_amount', '>', 0)->oldest('returned_at')->limit(6)->get(),
            'payouts' => WithdrawalRequest::with(['customer:id,code,name', 'payoutAccount:id,customer_id,bank_name,account_number'])->whereIn('status', ['submitted', 'approved'])->oldest('submitted_at')->limit(6)->get(),
            'holds' => ProductHold::with(['product:id,code,name,availability_status', 'customer:id,code,name'])->where('status', 'active')->oldest('hold_until')->limit(6)->get(),
        ]);
    }
}
