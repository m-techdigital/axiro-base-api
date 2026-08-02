<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\TransactionActionRequest;
use App\Http\Requests\Common\ListQueryRequest;
use App\Http\Requests\TransactionRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Transaction;
use App\Services\AuditTrailService;
use App\Services\Marketplace\Operations\MarketplaceOperationsReadService;
use App\Services\Marketplace\TransactionLifecycleService;
use App\Support\Marketplace\MoneyMath;
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

    public function show(
        Transaction $transaction,
        AuditTrailService $audit,
        TransactionLifecycleService $lifecycle,
        MarketplaceOperationsReadService $operations,
    )
    {
        $loaded = $transaction->load([
            'product',
            'product.rentalRates',
            'buyer:id,code,name,avatar_url',
            'seller:id,code,name,avatar_url',
            'payments.customer:id,code,name',
            'events',
            'disputes.openedBy:id,code,name',
            'checkpoints.customer:id,code,name',
            'documents.template:id,code,name,type',
            'documents.acceptances.customer:id,code,name',
        ]);

        $loaded->setAttribute('audit_history', $audit->forTransaction($transaction->id));
        $loaded->setAttribute('admin_actions', $lifecycle->allowedAdminActions($loaded));
        $loaded->setAttribute('workflow_checklist', $operations->documentChecklist($loaded));

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
                isset($data['rental_deposit_deduction_amount']) ? (string) $data['rental_deposit_deduction_amount'] : null,
                $data['rental_deposit_deduction_note'] ?? null,
            ),
        );
    }

    public function destroy(Transaction $transaction)
    {
        $transaction->delete();

        return ApiResponse::success(message: 'Đã xóa giao dịch.');
    }

    private function prepare(array $data, bool $creating = false): array
    {
        $data['total_payable'] = MoneyMath::subtract(
            MoneyMath::add(
                MoneyMath::add(
                    (string) $data['transaction_value'],
                    (string) ($data['service_fee'] ?? '0.00'),
                ),
                (string) ($data['deposit_amount'] ?? '0.00'),
            ),
            (string) ($data['discount'] ?? '0.00'),
        );
        $data['updated_by'] = user_id();

        if ($creating) {
            $data['created_by'] = user_id();
        }

        return $data;
    }
}
