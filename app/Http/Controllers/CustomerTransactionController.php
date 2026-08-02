<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customer\OpenDisputeRequest;
use App\Http\Requests\Customer\SubmitPaymentRequest;
use App\Http\Requests\Customer\TransactionActionRequest;
use App\Http\Requests\Customer\TransactionCreateRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Services\Marketplace\TransactionLifecycleService;
use App\Services\Payments\MarketplaceQrService;
use Illuminate\Http\Request;

class CustomerTransactionController extends Controller
{
    public function index(Request $request)
    {
        $customerId = auth('customer_api')->id();
        $query = Transaction::with(['product.rentalRates', 'buyer:id,code,name', 'seller:id,code,name', 'payments', 'documents.acceptances'])
            ->where(fn ($nested) => $nested->where('buyer_customer_id', $customerId)->orWhere('seller_customer_id', $customerId));
        if ($request->filled('role')) {
            $query->where($request->string('role') === 'seller' ? 'seller_customer_id' : 'buyer_customer_id', $customerId);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return ApiResponse::paginated($query->latest()->paginate(min(100, max(1, $request->integer('per_page', 20)))));
    }

    public function show(Transaction $transaction, TransactionLifecycleService $service)
    {
        $this->authorizeParty($transaction);
        $loaded = $transaction->load(['product.rentalRates', 'buyer:id,code,name,avatar_url', 'seller:id,code,name,avatar_url', 'payments', 'events', 'disputes:id,transaction_id,status,reason,description,resolution,outcome,resolved_at', 'checkpoints.customer:id,code,name']);
        $loaded->setAttribute('current_role', $transaction->buyer_customer_id === auth('customer_api')->id() ? 'buyer' : 'seller');
        $loaded->setAttribute('allowed_actions', $service->allowedActions($transaction, auth('customer_api')->id()));

        return ApiResponse::success($loaded);
    }

    public function createFromProduct(TransactionCreateRequest $request, Product $product, TransactionLifecycleService $service)
    {
        return ApiResponse::success($service->createFromProduct($product, auth('customer_api')->id(), $request->validated()), 'Đã tạo giao dịch.', 201);
    }

    public function paymentQr(Transaction $transaction, TransactionPayment $payment, MarketplaceQrService $qr)
    {
        $this->authorizeParty($transaction);
        abort_unless($payment->transaction_id === $transaction->id, 404);

        return ApiResponse::success($qr->make($payment->code, $payment->amount));
    }

    public function submitPayment(SubmitPaymentRequest $request, Transaction $transaction, TransactionPayment $payment, TransactionLifecycleService $service)
    {
        $this->authorizeParty($transaction);
        abort_unless($payment->transaction_id === $transaction->id, 404);

        return ApiResponse::success($service->submitPayment($payment, auth('customer_api')->id(), $request->validated()));
    }

    public function action(TransactionActionRequest $request, Transaction $transaction, TransactionLifecycleService $service)
    {
        $this->authorizeParty($transaction);

        return ApiResponse::success($service->transition($transaction, $request->validated('action'), 'customer', auth('customer_api')->id()));
    }

    public function openDispute(OpenDisputeRequest $request, Transaction $transaction, TransactionLifecycleService $service)
    {
        $this->authorizeParty($transaction);

        return ApiResponse::success($service->openDispute($transaction, auth('customer_api')->id(), $request->validated()), 'Đã tạo tranh chấp.', 201);
    }

    private function authorizeParty(Transaction $transaction): void
    {
        abort_unless(in_array(auth('customer_api')->id(), [$transaction->buyer_customer_id, $transaction->seller_customer_id], true), 403);
    }
}
