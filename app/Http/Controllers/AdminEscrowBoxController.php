<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\EscrowBoxHandoverReviewRequest;
use App\Http\Requests\Admin\EscrowBoxReviewRequest;
use App\Http\Requests\Common\ListQueryRequest;
use App\Http\Responses\ApiResponse;
use App\Models\EscrowBox;
use App\Models\EscrowBoxHandoverStep;
use App\Models\EscrowFeeRule;
use App\Services\Marketplace\EscrowBoxPresenter;
use App\Services\Marketplace\EscrowBoxService;
use App\Support\Query\AppliesListQuery;
use Illuminate\Http\Request;

class AdminEscrowBoxController extends Controller
{
    use AppliesListQuery;

    public function index(ListQueryRequest $request)
    {
        $query = $this->applyListFilters(EscrowBox::query()->with(['partyA:id,code,name,username,status', 'partyB:id,code,name,username,status']), $request, ['code'], ['status', 'risk_level', 'deal_type'], ['id', 'code', 'status', 'risk_level', 'final_fee', 'created_at']);
        return ApiResponse::paginated($query->paginate($request->perPage()));
    }

    public function show(EscrowBox $escrowBox, EscrowBoxPresenter $presenter)
    {
        return ApiResponse::success($presenter->admin($escrowBox->load(['partyA', 'partyB', 'agreementVersions', 'obligations', 'handoverSteps.media', 'events', 'transaction.payments'])));
    }

    public function review(EscrowBoxReviewRequest $request, EscrowBox $escrowBox, EscrowBoxService $service, EscrowBoxPresenter $presenter)
    {
        return ApiResponse::success($presenter->admin($service->adminReview($escrowBox, user_id(), $request->validated())), 'Đã cập nhật kết quả thẩm định box.');
    }

    public function reviewHandover(EscrowBoxHandoverReviewRequest $request, EscrowBox $escrowBox, EscrowBoxHandoverStep $step, EscrowBoxService $service, EscrowBoxPresenter $presenter)
    {
        abort_unless((int) $step->escrow_box_id === (int) $escrowBox->id, 404);
        return ApiResponse::success($presenter->admin($service->reviewHandover($escrowBox, $step, user_id(), $request->validated())), 'Đã cập nhật checkpoint bàn giao.');
    }

    public function feeRules()
    {
        return ApiResponse::success(EscrowFeeRule::query()->orderBy('priority')->get());
    }

    public function storeFeeRule(Request $request)
    {
        $data = $this->feeRuleData($request);
        $data['created_by'] = user_id();
        $data['updated_by'] = user_id();
        return ApiResponse::success(EscrowFeeRule::query()->create($data), 'Đã tạo quy tắc phí.', 201);
    }

    public function updateFeeRule(Request $request, EscrowFeeRule $rule)
    {
        $data = $this->feeRuleData($request);
        $data['version'] = $rule->version + 1;
        $data['updated_by'] = user_id();
        $rule->update($data);
        return ApiResponse::success($rule->fresh(), 'Đã cập nhật quy tắc phí.');
    }

    private function feeRuleData(Request $request): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50'], 'name' => ['required', 'string', 'max:180'],
            'minimum_money_amount' => ['required', 'numeric', 'min:0'], 'maximum_money_amount' => ['nullable', 'numeric', 'gte:minimum_money_amount'],
            'base_fee' => ['required', 'numeric', 'min:0'], 'percentage_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'minimum_fee' => ['required', 'numeric', 'min:0'], 'maximum_fee' => ['nullable', 'numeric', 'gte:minimum_fee'],
            'priority' => ['required', 'integer', 'min:1'], 'is_active' => ['required', 'boolean'],
            'effective_from' => ['nullable', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);
    }
}
