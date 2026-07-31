<?php

namespace App\Http\Controllers;

use App\Http\Requests\Common\ListQueryRequest;
use App\Http\Requests\ContractRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Contract;
use App\Models\Transaction;
use App\Support\Query\AppliesListQuery;
use Illuminate\Http\JsonResponse;

class ContractController extends Controller
{
    use AppliesListQuery;

    public function index(ListQueryRequest $request)
    {
        $query = $this->applyListFilters(
            Contract::with('transaction.product'),
            $request,
            ['code', 'title'],
            ['status'],
            ['id', 'code', 'title', 'status', 'contract_value', 'created_at'],
        );

        return ApiResponse::paginated($query->paginate($request->perPage()));
    }

    public function store(ContractRequest $request)
    {
        $data = $this->prepare($request->validated(), true);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        return ApiResponse::success(
            Contract::create($data)->load('transaction.product'),
            'Đã tạo hợp đồng.',
            201,
        );
    }

    public function show(Contract $contract)
    {
        return ApiResponse::success($contract->load('transaction.product'));
    }

    public function update(ContractRequest $request, Contract $contract)
    {
        $data = $this->prepare($request->validated());

        if ($data instanceof JsonResponse) {
            return $data;
        }

        $contract->update($data);

        return ApiResponse::success(
            $contract->fresh()->load('transaction.product'),
            'Đã cập nhật hợp đồng.',
        );
    }

    public function destroy(Contract $contract)
    {
        $contract->delete();

        return ApiResponse::success(message: 'Đã xóa hợp đồng.');
    }

    private function prepare(array $data, bool $creating = false): array|JsonResponse
    {
        $transaction = Transaction::findOrFail($data['transaction_id']);

        if ($transaction->status === 'cancelled') {
            return ApiResponse::error(
                'Không thể sử dụng giao dịch đã hủy.',
                null,
                422,
            );
        }

        $data['contract_value'] ??= $transaction->total_payable;
        $data['updated_by'] = user_id();

        if ($creating) {
            $data['created_by'] = user_id();
        }

        return $data;
    }
}
