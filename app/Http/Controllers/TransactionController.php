<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\TransactionActionRequest;
use App\Http\Requests\Common\ListQueryRequest;
use App\Http\Requests\TransactionRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Transaction;
use App\Services\AuditTrailService;
use App\Services\Marketplace\TransactionLifecycleService;
use App\Support\Query\AppliesListQuery;

class TransactionController extends Controller
{
    use AppliesListQuery;

    public function index(ListQueryRequest $request)
    {
        $query = $this->applyListFilters(
            Transaction::with([
                'product',
                'product.rentalRates',
                'buyer:id,code,name',
                'seller:id,code,name',
                'contract',
            ]),
            $request,
            ['code'],
            ['status'],
            ['id', 'code', 'status', 'transaction_date', 'total_payable', 'created_at'],
        );

        return ApiResponse::paginated($query->paginate($request->perPage()));
    }

    public function store(TransactionRequest $request)
    {
        $data = $this->prepare($request->validated(), true);
        $transaction = Transaction::create($data);

        return ApiResponse::success(
            $transaction->load([
                'product',
                'product.rentalRates',
                'buyer:id,code,name',
                'seller:id,code,name',
            ]),
            'Đã tạo giao dịch.',
            201,
        );
    }

    public function show(Transaction $transaction, AuditTrailService $audit)
    {
        $loaded = $transaction->load([
            'product',
            'product.rentalRates',
            'buyer:id,code,name,avatar_url',
            'seller:id,code,name,avatar_url',
            'contract',
            'payments.customer:id,code,name',
            'events',
            'disputes.openedBy:id,code,name',
            'checkpoints.customer:id,code,name',
            'documents.template:id,code,name,type',
            'documents.acceptances.customer:id,code,name',
        ]);

        $loaded->setAttribute('audit_history', $audit->forTransaction($transaction->id));

        return ApiResponse::success($loaded);
    }

    public function update(TransactionRequest $request, Transaction $transaction)
    {
        $transaction->update($this->prepare($request->validated()));

        return ApiResponse::success(
            $transaction->fresh()->load([
                'product',
                'product.rentalRates',
                'buyer:id,code,name',
                'seller:id,code,name',
            ]),
            'Đã cập nhật giao dịch.',
        );
    }

    public function action(
        TransactionActionRequest $request,
        Transaction $transaction,
        TransactionLifecycleService $service,
    ) {
        $data = $request->validated();

        return ApiResponse::success(
            $service->adminTransition(
                $transaction,
                $data['action'],
                user_id(),
                $data['note'] ?? null,
            ),
        );
    }

    public function destroy(Transaction $transaction)
    {
        if ($transaction->contract()->exists()) {
            return ApiResponse::error(
                'Giao dịch đã có hợp đồng nên không thể xóa.',
                null,
                409,
            );
        }

        $transaction->delete();

        return ApiResponse::success(message: 'Đã xóa giao dịch.');
    }

    private function prepare(array $data, bool $creating = false): array
    {
        $data['total_payable'] = number_format(
            (float) $data['transaction_value']
                + (float) ($data['service_fee'] ?? 0)
                + (float) ($data['deposit_amount'] ?? 0)
                - (float) ($data['discount'] ?? 0),
            2,
            '.',
            '',
        );
        $data['updated_by'] = user_id();

        if ($creating) {
            $data['created_by'] = user_id();
        }

        return $data;
    }
}
