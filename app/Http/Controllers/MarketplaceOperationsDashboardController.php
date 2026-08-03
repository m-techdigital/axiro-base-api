<?php

namespace App\Http\Controllers;

use App\Exceptions\Marketplace\ProductHoldReleaseConflict;
use App\Http\Requests\Admin\ManualReleaseProductHoldRequest;
use App\Http\Requests\Common\ListQueryRequest;
use App\Http\Responses\ApiResponse;
use App\Models\MarketplaceExportRequest;
use App\Models\Product;
use App\Models\ProductHold;
use App\Models\Transaction;
use App\Services\Marketplace\Operations\MarketplaceOperationsReadService;
use App\Services\Marketplace\Operations\OperationalTimelinePresenter;
use App\Services\Marketplace\Operations\ProductHoldReleaseService;
use App\Services\Marketplace\Operations\RentalSettlementExportService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MarketplaceOperationsDashboardController extends Controller
{
    public function overview(MarketplaceOperationsReadService $service)
    {
        return ApiResponse::success($service->overview());
    }

    public function today(MarketplaceOperationsReadService $service)
    {
        return ApiResponse::success($service->todayWork());
    }

    public function timeline(string $subjectType, int $subjectId, OperationalTimelinePresenter $presenter)
    {
        return ApiResponse::success($presenter->present($subjectType, $subjectId));
    }

    public function holds(ListQueryRequest $request, MarketplaceOperationsReadService $service)
    {
        return ApiResponse::paginated($service->holds($request->filters(['state', 'status', 'product_id', 'customer_id']), $request->perPage()));
    }

    public function releaseHold(ManualReleaseProductHoldRequest $request, ProductHold $hold, ProductHoldReleaseService $service)
    {
        try {
            $product = $service->release($hold, $request->validated());
        } catch (ProductHoldReleaseConflict $exception) {
            return ApiResponse::error($exception->getMessage(), null, 409);
        }

        return ApiResponse::success($product, 'Đã nhả giữ chỗ sản phẩm.');
    }

    public function availabilityTimeline(Product $product)
    {
        return ApiResponse::success([
            'product' => $product->only(['id', 'code', 'name', 'availability_status', 'availability_version', 'hold_expires_at']),
            'active_hold' => $product->activeHold()->with('customer:id,code,name')->first(),
            'timeline' => $product->availabilityHistory()->with('customer:id,code,name')->latest()->limit(100)->get(),
        ]);
    }

    public function queues(ListQueryRequest $request, MarketplaceOperationsReadService $service)
    {
        return ApiResponse::paginated($service->stuckTransactions($request->filters(['queue', 'status', 'age_minutes']), $request->perPage()));
    }

    public function idempotency(ListQueryRequest $request, MarketplaceOperationsReadService $service)
    {
        return ApiResponse::paginated($service->idempotencyAudit($request->perPage()));
    }

    public function reconciliation(MarketplaceOperationsReadService $service)
    {
        return ApiResponse::success($service->reconciliation());
    }

    public function rentalSettlements(ListQueryRequest $request, MarketplaceOperationsReadService $service)
    {
        return ApiResponse::paginated($service->rentalSettlements(
            $request->filters(['status', 'transaction_id', 'customer_id', 'date_from', 'date_to']),
            $request->perPage(),
        ));
    }

    public function exportRentalSettlements(
        ListQueryRequest $request,
        MarketplaceOperationsReadService $readService,
        RentalSettlementExportService $exportService,
    ): StreamedResponse {
        return $exportService->stream(
            $request->filters(RentalSettlementExportService::FILTERS),
            $readService,
        );
    }

    public function requestRentalSettlementExport(
        ListQueryRequest $request,
        RentalSettlementExportService $service,
    ) {
        return ApiResponse::success(
            $service->request($request->filters(RentalSettlementExportService::FILTERS)),
            'Đã đưa yêu cầu xuất dữ liệu vào hàng đợi.',
            202,
        );
    }

    public function rentalSettlementExportStatus(
        MarketplaceExportRequest $exportRequest,
        RentalSettlementExportService $service,
    ) {
        $service->assertRentalSettlement($exportRequest);

        return ApiResponse::success($exportRequest);
    }

    public function downloadRentalSettlementExport(
        MarketplaceExportRequest $exportRequest,
        RentalSettlementExportService $service,
    ): BinaryFileResponse|Response {
        return $service->download($exportRequest);
    }

    public function documentChecklist(Transaction $transaction, MarketplaceOperationsReadService $service)
    {
        return ApiResponse::success($service->documentChecklist($transaction));
    }
}
