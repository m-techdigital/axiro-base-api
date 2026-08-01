<?php

namespace App\Http\Controllers;

use App\Http\Requests\Common\ListQueryRequest;
use App\Http\Responses\ApiResponse;
use App\Models\TransactionPayment;
use App\Services\Marketplace\MarketplaceNotificationService;
use App\Services\Marketplace\TransactionLifecycleService;
use App\Support\Query\AppliesListQuery;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use AppliesListQuery;

    public function index(ListQueryRequest $request)
    {
        $query = $this->applyListFilters(
            TransactionPayment::with(['transaction.product', 'customer:id,code,name']),
            $request,
            ['code', 'reference'],
            ['status', 'payment_type', 'transaction_id', 'customer_id'],
            ['id', 'code', 'status', 'payment_type', 'amount', 'due_date', 'created_at'],
        );

        return ApiResponse::paginated($query->paginate($request->perPage()));
    }

    public function confirm(
        TransactionPayment $payment,
        TransactionLifecycleService $service,
    ) {
        return ApiResponse::success($service->confirmPayment($payment, user_id()));
    }

    public function reject(
        Request $request,
        TransactionPayment $payment,
        MarketplaceNotificationService $notifications,
    ) {
        $data = $request->validate(['note' => 'required|string|max:2000']);
        $payment->update(['status' => 'rejected', 'note' => $data['note']]);
        $payment->load('transaction');
        $notifications->transaction(
            $payment->customer_id,
            'payment_rejected',
            'Thanh toán cần gửi lại',
            $data['note'],
            $payment->transaction_id,
            $payment->transaction->code,
        );

        return ApiResponse::success($payment->fresh());
    }
}
