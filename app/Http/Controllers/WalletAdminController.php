<?php

namespace App\Http\Controllers;

use App\Http\Requests\Common\ListQueryRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletLedgerService;
use App\Support\Query\AppliesListQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WalletAdminController extends Controller
{
    use AppliesListQuery;

    public function index(ListQueryRequest $request)
    {
        $query = WalletTransaction::with('customer:id,code,name,username')
            ->where('type', 'deposit_request');

        $query = $this->applyListFilters(
            $query,
            $request,
            ['code', 'external_reference'],
            ['status', 'customer_id'],
            ['id', 'code', 'status', 'amount', 'created_at', 'updated_at'],
        );

        return ApiResponse::paginated($query->paginate($request->perPage()));
    }

    public function wallets(ListQueryRequest $request)
    {
        $query = $this->applyListFilters(
            Customer::with('wallet'),
            $request,
            ['name', 'username', 'code', 'phone', 'email'],
            ['status'],
            ['id', 'code', 'name', 'username', 'status', 'created_at'],
            'name',
        );

        return ApiResponse::paginated($query->paginate($request->perPage()));
    }

    public function ledger(Request $request, Customer $customer)
    {
        $wallet = CustomerWallet::firstOrCreate(['customer_id' => $customer->id]);
        $items = WalletTransaction::where('customer_id', $customer->id)
            ->latest('occurred_at')
            ->latest()
            ->paginate(min(100, max(1, $request->integer('per_page', 50))));

        return ApiResponse::success([
            'customer' => $customer->only(['id', 'code', 'name', 'username']),
            'wallet' => $wallet,
            'transactions' => $items,
        ]);
    }

    public function adjust(
        Request $request,
        Customer $customer,
        WalletLedgerService $ledger,
    ) {
        $data = $request->validate([
            'direction' => 'required|in:credit,debit',
            'bucket' => 'required|in:available,held',
            'amount' => 'required|numeric|min:1',
            'note' => 'required|string|max:1000',
        ]);
        $method = $data['direction'] === 'credit'
            ? ($data['bucket'] === 'held' ? 'creditHeld' : 'creditAvailable')
            : ($data['bucket'] === 'held' ? 'debitHeld' : 'debitAvailable');
        $entry = $ledger->{$method}(
            $customer->id,
            (string) $data['amount'],
            'admin_adjustment',
            [
                'idempotency_key' => 'admin-adjust:'.Str::uuid(),
                'confirmed_by' => user_id(),
                'note' => $data['note'],
                'metadata' => ['admin_id' => user_id()],
            ],
        );

        return ApiResponse::success($entry);
    }

    public function confirm(
        WalletTransaction $walletTransaction,
        WalletLedgerService $ledger,
    ) {
        return DB::transaction(function () use ($walletTransaction, $ledger) {
            $entry = WalletTransaction::lockForUpdate()->findOrFail($walletTransaction->id);

            if ($entry->status === 'confirmed') {
                return ApiResponse::success($entry->fresh('customer'));
            }

            if (! in_array($entry->status, ['pending', 'submitted'], true)
                || $entry->type !== 'deposit_request') {
                throw ValidationException::withMessages([
                    'status' => 'Yêu cầu nạp tiền không còn ở trạng thái chờ xác nhận.',
                ]);
            }

            $credit = $ledger->creditAvailable(
                $entry->customer_id,
                (string) $entry->amount,
                'deposit_confirmed',
                [
                    'idempotency_key' => 'deposit-confirm:'.$entry->id,
                    'payment_method' => $entry->payment_method,
                    'external_reference' => $entry->external_reference,
                    'reference_type' => 'wallet_deposit',
                    'reference_id' => $entry->id,
                    'confirmed_by' => user_id(),
                    'note' => $entry->note,
                ],
            );

            $entry->update([
                'status' => 'confirmed',
                'review_note' => null,
                'available_before' => $credit->available_before,
                'available_after' => $credit->available_after,
                'held_before' => $credit->held_before,
                'held_after' => $credit->held_after,
                'balance_after' => $credit->available_after,
                'confirmed_at' => now(),
                'confirmed_by' => user_id(),
            ]);

            return ApiResponse::success($entry->fresh('customer'));
        });
    }

    public function reject(Request $request, WalletTransaction $walletTransaction)
    {
        $data = $request->validate(['note' => 'required|string|max:2000']);
        abort_unless(in_array($walletTransaction->status, ['pending', 'submitted'], true), 422);
        $walletTransaction->update([
            'status' => 'rejected',
            'review_note' => $data['note'],
            'confirmed_at' => now(),
            'confirmed_by' => user_id(),
        ]);

        return ApiResponse::success($walletTransaction->fresh('customer'));
    }
}
