<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\MarkWithdrawalPaidRequest;
use App\Http\Requests\Admin\RejectWithdrawalRequest;
use App\Models\CustomerPayoutAccount;
use App\Models\CustomerVerification;
use App\Models\WithdrawalRequest;
use App\Services\AuditTrailService;
use App\Services\Payouts\PayoutJourneyPresenter;
use App\Services\Payouts\WithdrawalService;
use Illuminate\Http\Request;

class PayoutAdminController extends Controller
{
    public function verifications(Request $r)
    {
        $q = CustomerVerification::with('customer:id,code,name,username,email,phone')->when($r->status, fn ($q, $v) => $q->where('status', $v))->latest();

        return success_response($q->paginate(min(100, max(1, $r->integer('per_page', 20)))));
    }

    public function reviewVerification(Request $r, CustomerVerification $verification)
    {
        $d = $r->validate(['decision' => 'required|in:verify,reject', 'note' => 'nullable|required_if:decision,reject|string|max:2000']);
        $verification->update(['status' => $d['decision'] === 'verify' ? 'verified' : 'rejected', 'verified_at' => $d['decision'] === 'verify' ? now() : null, 'verified_by' => user_id(), 'review_note' => $d['note'] ?? null]);
        app(AuditTrailService::class)->log([
            'event_type' => 'seller_verification_'.$d['decision'], 'actor_type' => 'admin', 'actor_id' => user_id(),
            'entity_type' => 'customer_verification', 'entity_id' => $verification->id,
            'context_type' => 'customer', 'context_id' => $verification->customer_id,
            'title' => 'Xử lý xác minh người bán', 'description' => $d['note'] ?? 'Không có ghi chú.',
            'metadata' => ['decision' => $d['decision'], 'status' => $verification->status],
        ]);

        return success_response($verification->fresh('customer'));
    }

    public function accounts(Request $r)
    {
        $q = CustomerPayoutAccount::with('customer:id,code,name,username')->when($r->status, fn ($q, $v) => $q->where('status', $v))->latest();

        return success_response($q->paginate(min(100, max(1, $r->integer('per_page', 20)))));
    }

    public function reviewAccount(Request $r, CustomerPayoutAccount $account)
    {
        $d = $r->validate(['decision' => 'required|in:verify,reject', 'note' => 'nullable|required_if:decision,reject|string|max:2000']);
        $account->update(['status' => $d['decision'] === 'verify' ? 'verified' : 'rejected', 'verified_at' => $d['decision'] === 'verify' ? now() : null, 'verified_by' => user_id(), 'review_note' => $d['note'] ?? null]);
        app(AuditTrailService::class)->log([
            'event_type' => 'payout_account_'.$d['decision'], 'actor_type' => 'admin', 'actor_id' => user_id(),
            'entity_type' => 'customer_payout_account', 'entity_id' => $account->id,
            'context_type' => 'customer', 'context_id' => $account->customer_id,
            'title' => 'Xử lý tài khoản nhận tiền', 'description' => $d['note'] ?? 'Không có ghi chú.',
            'metadata' => ['decision' => $d['decision'], 'status' => $account->status],
        ]);

        return success_response($account->fresh('customer'));
    }

    public function withdrawals(Request $r, PayoutJourneyPresenter $presenter)
    {
        $page = WithdrawalRequest::with([
            'customer:id,code,name,username',
            'customer.verification:id,customer_id,status,review_note',
            'customer.wallet:id,customer_id,available_balance,held_balance',
            'payoutAccount',
        ])
            ->when($r->status, fn ($q, $v) => $q->where('status', $v))
            ->latest()
            ->paginate(min(100, max(1, $r->integer('per_page', 20))));

        $page->getCollection()->transform(function (WithdrawalRequest $withdrawal) use ($presenter) {
            $withdrawal->setAttribute('journey', $presenter->adminWithdrawal($withdrawal));

            return $withdrawal;
        });

        return success_response($page);
    }

    public function approve(WithdrawalRequest $withdrawal, WithdrawalService $service)
    {
        return success_response($service->approve($withdrawal, user_id()));
    }

    public function reject(RejectWithdrawalRequest $request, WithdrawalRequest $withdrawal, WithdrawalService $service)
    {
        return success_response($service->reject($withdrawal, user_id(), $request->validated('note')));
    }

    public function paid(MarkWithdrawalPaidRequest $request, WithdrawalRequest $withdrawal, WithdrawalService $service)
    {
        $data = $request->validated();

        return success_response($service->markPaid($withdrawal, user_id(), $data['payment_reference'], $data['proof_url'] ?? null));
    }
}
