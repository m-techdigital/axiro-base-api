<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customer\DepositProofRequest;
use App\Http\Requests\Customer\DepositRequest;
use App\Http\Resources\WalletDepositResource;
use App\Http\Resources\WalletLedgerEntryResource;
use App\Http\Responses\ApiResponse;
use App\Models\CustomerWallet;
use App\Models\WalletTransaction;
use App\Services\Payments\MarketplaceQrService;
use App\Support\Http\PaginationMeta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerWalletController extends Controller
{
    public function index(Request $request)
    {
        $customerId = auth('customer_api')->id();
        $wallet = CustomerWallet::firstOrCreate(['customer_id' => $customerId]);
        $transactions = WalletTransaction::query()
            ->where('customer_id', $customerId)
            ->where(fn ($query) => $query->where('type', '!=', 'deposit_request')->orWhere('status', 'confirmed'))
            ->when($request->type, fn ($query, $value) => $query->where('type', $value))
            ->when($request->status, fn ($query, $value) => $query->where('status', $value))
            ->when($request->transaction_id, fn ($query, $value) => $query->where('transaction_id', $value))
            ->latest('occurred_at')->latest()
            ->paginate(min(100, max(1, $request->integer('per_page', 20))));

        return ApiResponse::success([
            'wallet' => [
                'available_balance' => $wallet->available_balance,
                'held_balance' => $wallet->held_balance,
                'total_balance' => bcadd((string) $wallet->available_balance, (string) $wallet->held_balance, 2),
                'pending_deposit_balance' => WalletTransaction::query()->where('customer_id', $customerId)->where('type', 'deposit_request')->whereIn('status', ['draft', 'submitted'])->sum('amount'),
            ],
            'transactions' => [
                'data' => WalletLedgerEntryResource::collection(collect($transactions->items()))->resolve(),
                'meta' => PaginationMeta::from($transactions),
            ],
        ]);
    }

    public function deposits(Request $request)
    {
        $items = WalletTransaction::query()->where('customer_id', auth('customer_api')->id())->where('type', 'deposit_request')
            ->latest('occurred_at')->latest()->paginate(min(100, max(1, $request->integer('per_page', 20))));

        return ApiResponse::paginated($items, WalletDepositResource::collection(collect($items->items()))->resolve());
    }

    public function deposit(DepositRequest $request, MarketplaceQrService $qrService)
    {
        $data = $request->validated();
        $customerId = auth('customer_api')->id();
        $wallet = CustomerWallet::firstOrCreate(['customer_id' => $customerId]);
        $code = 'NAP-'.now()->format('ymd').'-'.strtoupper(Str::random(6));
        $payment = $qrService->make($code, $data['amount']);
        $transaction = WalletTransaction::create([
            'code' => $code, 'idempotency_key' => 'deposit:'.$customerId.':'.Str::uuid(), 'customer_id' => $customerId,
            'type' => 'deposit_request', 'direction' => 'credit', 'balance_bucket' => 'available', 'amount' => $data['amount'],
            'available_before' => $wallet->available_balance, 'available_after' => $wallet->available_balance,
            'held_before' => $wallet->held_balance, 'held_after' => $wallet->held_balance, 'balance_after' => $wallet->available_balance,
            'status' => 'draft', 'payment_method' => $data['payment_method'], 'external_reference' => $payment['transfer_content'],
            'metadata' => $payment, 'note' => $data['note'] ?? null, 'occurred_at' => now(),
        ]);

        return ApiResponse::success((new WalletDepositResource($transaction->fresh()))->resolve(), 'Đã tạo yêu cầu nạp tiền.', 201);
    }

    public function showDeposit(WalletTransaction $walletTransaction)
    {
        $this->authorizeDeposit($walletTransaction);

        return ApiResponse::success((new WalletDepositResource($walletTransaction))->resolve());
    }

    public function submitProof(DepositProofRequest $request, WalletTransaction $walletTransaction)
    {
        $this->authorizeDeposit($walletTransaction);
        if (! in_array($walletTransaction->status, ['draft', 'rejected'], true)) {
            throw ValidationException::withMessages(['status' => 'Yêu cầu nạp tiền không thể gửi lại ở trạng thái hiện tại.']);
        }
        $data = $request->validated();
        $path = $data['proof']->store('marketplace/deposit-proofs', 'public');
        $walletTransaction->update([
            'proof_image_url' => Storage::disk('public')->url($path),
            'external_reference' => $data['external_reference'] ?? $walletTransaction->external_reference,
            'note' => $data['note'] ?? $walletTransaction->note,
            'status' => 'submitted', 'submitted_at' => now(), 'review_note' => null,
        ]);

        return ApiResponse::success((new WalletDepositResource($walletTransaction->fresh()))->resolve(), 'Đã gửi chứng từ. Yêu cầu đang chờ đối soát.');
    }

    private function authorizeDeposit(WalletTransaction $entry): void
    {
        abort_unless($entry->customer_id === auth('customer_api')->id() && $entry->type === 'deposit_request', 404);
    }
}
