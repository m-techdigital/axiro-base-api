<?php

namespace App\Http\Controllers;

use App\Models\CustomerWallet;
use App\Models\WalletTransaction;
use App\Services\Payments\MarketplaceQrService;
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
            ->where(function ($query) {
                $query->where('type', '!=', 'deposit_request')
                    ->orWhere('status', 'confirmed');
            })
            ->when($request->type, fn ($query, $value) => $query->where('type', $value))
            ->when($request->status, fn ($query, $value) => $query->where('status', $value))
            ->when($request->transaction_id, fn ($query, $value) => $query->where('transaction_id', $value))
            ->latest('occurred_at')
            ->latest()
            ->paginate(min(100, max(1, $request->integer('per_page', 20))))
            ->through(fn (WalletTransaction $entry) => $this->presentLedgerEntry($entry));

        return success_response([
            'wallet' => [
                'available_balance' => $wallet->available_balance,
                'held_balance' => $wallet->held_balance,
                'total_balance' => bcadd((string) $wallet->available_balance, (string) $wallet->held_balance, 2),
                'pending_deposit_balance' => WalletTransaction::query()->where('customer_id', $customerId)->where('type', 'deposit_request')->whereIn('status', ['draft', 'submitted'])->sum('amount'),
            ],
            'transactions' => $transactions,
        ]);
    }


    public function deposits(Request $request)
    {
        $customerId = auth('customer_api')->id();
        $items = WalletTransaction::query()
            ->where('customer_id', $customerId)
            ->where('type', 'deposit_request')
            ->latest('occurred_at')
            ->latest()
            ->paginate(min(100, max(1, $request->integer('per_page', 20))))
            ->through(fn (WalletTransaction $entry) => $this->presentDeposit($entry));

        return success_response($items);
    }

    public function deposit(Request $request, MarketplaceQrService $qrService)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:10000|max:100000000',
            'payment_method' => 'required|in:bank,momo',
            'note' => 'nullable|string|max:1000',
        ]);

        $customerId = auth('customer_api')->id();
        $wallet = CustomerWallet::firstOrCreate(['customer_id' => $customerId]);
        $code = 'NAP-'.now()->format('ymd').'-'.strtoupper(Str::random(6));
        $payment = $qrService->make($code, $data['amount']);

        $transaction = WalletTransaction::create([
            'code' => $code,
            'idempotency_key' => 'deposit:'.$customerId.':'.Str::uuid(),
            'customer_id' => $customerId,
            'type' => 'deposit_request',
            'direction' => 'credit',
            'balance_bucket' => 'available',
            'amount' => $data['amount'],
            'available_before' => $wallet->available_balance,
            'available_after' => $wallet->available_balance,
            'held_before' => $wallet->held_balance,
            'held_after' => $wallet->held_balance,
            'balance_after' => $wallet->available_balance,
            'status' => 'draft',
            'payment_method' => $data['payment_method'],
            'external_reference' => $payment['transfer_content'],
            'metadata' => $payment,
            'note' => $data['note'] ?? null,
            'occurred_at' => now(),
        ]);

        return success_response($this->presentDeposit($transaction->fresh()), 'Đã tạo yêu cầu nạp tiền.', 201);
    }

    public function showDeposit(WalletTransaction $walletTransaction)
    {
        abort_unless(
            $walletTransaction->customer_id === auth('customer_api')->id()
            && $walletTransaction->type === 'deposit_request',
            404
        );

        return success_response($this->presentDeposit($walletTransaction));
    }

    public function submitProof(Request $request, WalletTransaction $walletTransaction)
    {
        abort_unless(
            $walletTransaction->customer_id === auth('customer_api')->id()
            && $walletTransaction->type === 'deposit_request',
            404
        );

        if (! in_array($walletTransaction->status, ['draft', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Yêu cầu nạp tiền không thể gửi lại ở trạng thái hiện tại.',
            ]);
        }

        $data = $request->validate([
            'proof' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'external_reference' => 'nullable|string|max:150',
            'note' => 'nullable|string|max:1000',
        ]);

        $path = $data['proof']->store('marketplace/deposit-proofs', 'public');
        $walletTransaction->update([
            'proof_image_url' => Storage::disk('public')->url($path),
            'external_reference' => $data['external_reference'] ?? $walletTransaction->external_reference,
            'note' => $data['note'] ?? $walletTransaction->note,
            'status' => 'submitted',
            'submitted_at' => now(),
            'review_note' => null,
        ]);

        return success_response(
            $this->presentDeposit($walletTransaction->fresh()),
            'Đã gửi chứng từ. Yêu cầu đang chờ đối soát.'
        );
    }

    private function presentLedgerEntry(WalletTransaction $entry): array
    {
        $balanceBefore = $entry->balance_bucket === 'held'
            ? $entry->held_before
            : $entry->available_before;
        $balanceAfter = $entry->balance_bucket === 'held'
            ? $entry->held_after
            : $entry->available_after;

        return [
            'id' => $entry->id,
            'type' => $entry->type,
            'direction' => $entry->direction,
            'balance_bucket' => $entry->balance_bucket,
            'amount' => $entry->amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'status' => $entry->status,
            'occurred_at' => $entry->occurred_at,
        ];
    }

    private function presentDeposit(WalletTransaction $entry): array
    {
        return [
            'id' => $entry->id,
            'code' => $entry->code,
            'amount' => $entry->amount,
            'status' => $entry->status,
            'payment_method' => $entry->payment_method,
            'metadata' => $entry->metadata,
            'proof_image_url' => $entry->proof_image_url,
            'external_reference' => $entry->external_reference,
            'note' => $entry->note,
            'review_note' => $entry->review_note,
            'occurred_at' => $entry->occurred_at,
            'submitted_at' => $entry->submitted_at,
            'confirmed_at' => $entry->confirmed_at,
        ];
    }
}
