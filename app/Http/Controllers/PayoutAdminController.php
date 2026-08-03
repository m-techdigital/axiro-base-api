<?php

namespace App\Http\Controllers;

use App\Models\CustomerPayoutAccount;
use App\Models\CustomerVerification;
use App\Models\WithdrawalRequest;
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

        return success_response($account->fresh('customer'));
    }

    public function withdrawals(Request $r)
    {
        $q = WithdrawalRequest::with(['customer:id,code,name,username', 'payoutAccount'])->when($r->status, fn ($q, $v) => $q->where('status', $v))->latest();

        return success_response($q->paginate(min(100, max(1, $r->integer('per_page', 20)))));
    }

    public function approve(WithdrawalRequest $withdrawal, WithdrawalService $service)
    {
        return success_response($service->approve($withdrawal, user_id()));
    }

    public function reject(Request $r, WithdrawalRequest $withdrawal, WithdrawalService $service)
    {
        $d = $r->validate(['note' => 'required|string|max:2000']);

        return success_response($service->reject($withdrawal, user_id(), $d['note']));
    }

    public function paid(Request $r, WithdrawalRequest $withdrawal, WithdrawalService $service)
    {
        $d = $r->validate(['payment_reference' => 'required|string|max:150', 'proof_url' => 'nullable|string|max:500']);

        return success_response($service->markPaid($withdrawal, user_id(), $d['payment_reference'], $d['proof_url'] ?? null));
    }
}
