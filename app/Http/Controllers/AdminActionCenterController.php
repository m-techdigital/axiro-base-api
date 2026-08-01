<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceDispute;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Models\WalletTransaction;

class AdminActionCenterController extends Controller
{
    public function __invoke()
    {
        return success_response([
            'counts' => [
                'pending_products' => Product::where('status', 'pending_review')->count(),
                'submitted_payments' => TransactionPayment::where('status', 'submitted')->count(),
                'overdue_payments' => TransactionPayment::where('status', 'overdue')->count(),
                'pending_deposits' => WalletTransaction::where('type', 'deposit_request')->where('status', 'pending')->count(),
                'open_disputes' => MarketplaceDispute::where('status', 'open')->count(),
                'handover_pending' => Transaction::whereIn('status', ['handover_pending', 'return_pending'])->count(),
                'overdue_rentals' => Transaction::where('transaction_type', 'rental')->where('status', 'overdue')->count(),
            ],
            'products' => Product::with(['owner:id,code,name', 'rentalRates'])->where('status', 'pending_review')->latest()->limit(6)->get(),
            'payments' => TransactionPayment::with(['transaction.product', 'customer:id,code,name'])->whereIn('status', ['submitted', 'overdue'])->latest()->limit(8)->get(),
            'deposits' => WalletTransaction::with('customer:id,code,name')->where('type', 'deposit_request')->where('status', 'pending')->latest()->limit(6)->get(),
            'disputes' => MarketplaceDispute::with(['transaction.product', 'openedBy:id,code,name'])->where('status', 'open')->latest()->limit(6)->get(),
            'transactions' => Transaction::with(['product', 'buyer:id,code,name', 'seller:id,code,name', 'checkpoints'])->whereIn('status', ['handover_pending', 'return_pending'])->latest()->limit(6)->get(),
        ]);
    }
}
