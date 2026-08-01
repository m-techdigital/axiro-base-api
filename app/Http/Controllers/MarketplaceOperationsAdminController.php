<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceCaseMessage;
use App\Models\MarketplaceDispute;
use App\Models\MarketplaceFeePolicy;
use App\Models\TransactionAssetSnapshot;
use App\Services\Marketplace\TransactionLifecycleService;
use Illuminate\Http\Request;

class MarketplaceOperationsAdminController extends Controller
{
    public function feePolicies(Request $r)
    {
        return success_response(MarketplaceFeePolicy::latest()->paginate(min(100, max(1, $r->integer('per_page', 20)))));
    }

    public function storeFeePolicy(Request $r)
    {
        $d = $this->feeData($r);
        $item = MarketplaceFeePolicy::create([...$d, 'created_by' => user_id(), 'updated_by' => user_id()]);

        return success_response($item, 'Đã tạo chính sách phí.', 201);
    }

    public function updateFeePolicy(Request $r, MarketplaceFeePolicy $policy)
    {
        $policy->update([...$this->feeData($r), 'updated_by' => user_id()]);

        return success_response($policy->fresh(), 'Đã cập nhật chính sách phí.');
    }

    private function feeData(Request $r): array
    {
        return $r->validate(['code' => 'required|string|max:50', 'name' => 'required|string|max:255', 'transaction_type' => 'nullable|in:purchase,rental', 'buyer_fee_rate' => 'required|numeric|min:0|max:100', 'buyer_fixed_fee' => 'required|numeric|min:0', 'seller_fee_rate' => 'required|numeric|min:0|max:100', 'seller_fixed_fee' => 'required|numeric|min:0', 'tax_rate' => 'required|numeric|min:0|max:100', 'priority' => 'required|integer|min:1|max:10000', 'is_active' => 'required|boolean', 'effective_from' => 'nullable|date', 'effective_to' => 'nullable|date|after_or_equal:effective_from', 'conditions' => 'nullable|array']);
    }

    public function cases(Request $r)
    {
        $q = MarketplaceDispute::with(['transaction:id,code,status,buyer_customer_id,seller_customer_id', 'openedBy:id,code,name', 'assignee:id,name'])->when($r->case_type, fn ($q, $v) => $q->where('case_type', $v))->when($r->status, fn ($q, $v) => $q->where('status', $v))->latest('last_message_at');

        return success_response($q->paginate(min(100, max(1, $r->integer('per_page', 20)))));
    }

    public function caseDetail(MarketplaceDispute $case)
    {
        return success_response($case->load(['transaction', 'openedBy:id,code,name', 'assignee:id,name', 'messages' => fn ($q) => $q->oldest()]));
    }

    public function updateCase(Request $r, MarketplaceDispute $case, TransactionLifecycleService $service)
    {
        $d = $r->validate(['status' => 'required|in:open,triaged,waiting_customer,waiting_counterparty,reviewing,resolved,rejected,cancelled', 'resolution' => 'nullable|required_if:status,resolved,rejected|string|max:4000', 'assigned_to' => 'nullable|integer|exists:users,id', 'priority' => 'nullable|in:low,normal,high,urgent', 'transaction_status' => 'nullable|in:completed,cancelled']);
        $case->update(['status' => $d['status'], 'resolution' => $d['resolution'] ?? $case->resolution, 'assigned_to' => $d['assigned_to'] ?? $case->assigned_to, 'priority' => $d['priority'] ?? $case->priority, 'resolved_at' => in_array($d['status'], ['resolved', 'rejected', 'cancelled'], true) ? now() : null, 'resolved_by' => in_array($d['status'], ['resolved', 'rejected', 'cancelled'], true) ? user_id() : null]);
        if (! empty($d['transaction_status'])) {
            $service->adminTransition($case->transaction, $d['transaction_status'] === 'completed' ? 'complete' : ($d['transaction_status'] === 'cancelled' ? 'cancel' : 'reopen'), user_id(), $d['resolution'] ?? null);
        }

return success_response($case->fresh(['transaction', 'messages']));
    }

    public function message(Request $r, MarketplaceDispute $case)
    {
        $d = $r->validate(['message' => 'required|string|max:4000', 'attachments' => 'nullable|array', 'is_internal' => 'nullable|boolean']);
        $item = MarketplaceCaseMessage::create(['case_id' => $case->id, 'actor_type' => 'user', 'actor_id' => user_id(), 'message' => $d['message'], 'attachments' => $d['attachments'] ?? [], 'is_internal' => $d['is_internal'] ?? false]);
        $case->update(['last_message_at' => now()]);

        return success_response($item, 'Đã thêm phản hồi.', 201);
    }

    public function snapshots(Request $r)
    {
        $q = TransactionAssetSnapshot::with(['transaction:id,code,status', 'customer:id,code,name'])->when($r->transaction_id, fn ($q, $v) => $q->where('transaction_id', $v))->latest('captured_at');

        return success_response($q->paginate(min(100,max(1,$r->integer('per_page',20)))));
    }
}
