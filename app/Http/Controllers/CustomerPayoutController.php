<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customer\CreateWithdrawalRequest;
use App\Models\CustomerPayoutAccount;
use App\Models\CustomerVerification;
use App\Models\CustomerWallet;
use App\Models\WithdrawalRequest;
use App\Services\MediaUploadService;
use App\Services\Payouts\PayoutJourneyPresenter;
use App\Services\Payouts\WithdrawalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerPayoutController extends Controller
{
    public function overview(PayoutJourneyPresenter $presenter)
    {
        $id = auth('customer_api')->id();
        $wallet = CustomerWallet::firstOrCreate(['customer_id' => $id]);
        $verification = CustomerVerification::firstOrCreate(
            ['customer_id' => $id],
            ['status' => 'unverified'],
        );
        $accounts = CustomerPayoutAccount::where('customer_id', $id)->latest()->get();
        $withdrawals = WithdrawalRequest::with('payoutAccount')
            ->where('customer_id', $id)
            ->latest()
            ->paginate(20);

        return success_response([
            'wallet' => $wallet,
            'verification' => $verification,
            'accounts' => $accounts,
            'withdrawals' => $withdrawals,
            'journey' => $presenter->customer($wallet, $verification, $accounts, $withdrawals),
        ]);
    }

    public function submitVerification(Request $r, MediaUploadService $media)
    {
        $d = $r->validate(['document_type' => 'required|in:citizen_id,passport', 'document_number' => 'required|string|max:80', 'document_front' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120', 'document_back' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120', 'selfie' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120']);
        $id = auth('customer_api')->id();
        $item = CustomerVerification::firstOrCreate(['customer_id' => $id]);
        if ($item->status === 'verified') {
            return success_response($item);
        }$front = $media->storeMany([$d['document_front']], 'marketplace/customer-verifications')[0]['url'];
        $back = isset($d['document_back']) ? $media->storeMany([$d['document_back']], 'marketplace/customer-verifications')[0]['url'] : null;
        $selfie = $media->storeMany([$d['selfie']], 'marketplace/customer-verifications')[0]['url'];
        $item->update(['document_type' => $d['document_type'], 'document_number' => $d['document_number'], 'document_front_url' => $front, 'document_back_url' => $back, 'selfie_url' => $selfie, 'status' => 'pending', 'submitted_at' => now(), 'verified_at' => null, 'verified_by' => null, 'review_note' => null]);

        return success_response($item->fresh(), 'Đã gửi hồ sơ xác minh.');
    }

    public function storeAccount(Request $r)
    {
        $d = $r->validate(['bank_code' => 'required|string|max:30', 'bank_name' => 'required|string|max:120', 'account_name' => 'required|string|max:150', 'account_number' => 'required|string|max:80', 'is_default' => 'sometimes|boolean']);
        $id = auth('customer_api')->id();

        return DB::transaction(function () use ($d, $id) {
            if (! empty($d['is_default'])) {
                CustomerPayoutAccount::where('customer_id', $id)->update(['is_default' => false]);
            }$item = CustomerPayoutAccount::create([...$d, 'customer_id' => $id, 'status' => 'pending']);

            return success_response($item, 'Đã thêm tài khoản nhận tiền.', 201);
        });
    }

    public function withdraw(CreateWithdrawalRequest $request, WithdrawalService $service)
    {
        $data = $request->validated();

        return success_response(
            $service->submit(
                auth('customer_api')->id(),
                (int) $data['payout_account_id'],
                (string) $data['amount'],
                $data['note'] ?? null,
                $data['idempotency_key'] ?? null,
            ),
            'Đã gửi yêu cầu rút tiền.',
            201,
        );
    }

    public function cancelWithdrawal(WithdrawalRequest $withdrawal, WithdrawalService $service)
    {
        return success_response(
            $service->cancelByCustomer($withdrawal, auth('customer_api')->id()),
            'Đã hủy yêu cầu rút tiền.',
        );
    }
}
