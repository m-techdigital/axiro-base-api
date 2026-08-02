<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\ManualReleaseProductHoldRequest;
use App\Http\Requests\Common\ListQueryRequest;
use App\Http\Responses\ApiResponse;
use App\Jobs\BuildRentalSettlementExport;
use App\Models\AuditLog;
use App\Models\MarketplaceExportRequest;
use App\Models\Product;
use App\Models\ProductHold;
use App\Models\Transaction;
use App\Services\Marketplace\Operations\MarketplaceOperationsReadService;
use App\Services\ProductAvailabilityService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MarketplaceOperationsDashboardController extends Controller
{
    public function overview(MarketplaceOperationsReadService $service)
    {
        return ApiResponse::success($service->overview());
    }

    public function holds(ListQueryRequest $request, MarketplaceOperationsReadService $service)
    {
        return ApiResponse::paginated($service->holds($request->filters(['state', 'status', 'product_id', 'customer_id']), $request->perPage()));
    }

    public function releaseHold(ManualReleaseProductHoldRequest $request, ProductHold $hold, ProductAvailabilityService $availability)
    {
        $data = $request->validated();
        $hold->loadMissing('product');
        $product = $hold->product;

        if ($hold->status !== 'active') {
            return ApiResponse::error('Lượt giữ chỗ này không còn hiệu lực.', null, 409);
        }
        if (! $product || $product->availability_status !== 'held' || (int) $product->held_by_transaction_id !== (int) $hold->source_id) {
            return ApiResponse::error('Sản phẩm không còn được giữ bởi lượt giữ chỗ này.', null, 409);
        }

        $updated = $availability->transition(
            $product,
            'available',
            $hold->source,
            $data['note'],
            $data['expected_version'] ?? null,
            true,
        );

        AuditLog::query()->create([
            'audit_type' => 'business_trail',
            'event_type' => 'product_hold_manual_release',
            'risk_level' => 'medium',
            'actor_type' => 'user',
            'actor_id' => user_id(),
            'entity_type' => 'product_hold',
            'entity_id' => (string) $hold->id,
            'context_type' => 'product',
            'context_id' => (string) $product->id,
            'title' => 'Nhả giữ chỗ thủ công',
            'description' => $data['note'],
            'metadata' => ['availability_version' => $updated->availability_version],
        ]);

        return ApiResponse::success($updated->load('activeHold'), 'Đã nhả giữ chỗ sản phẩm.');
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

    public function exportRentalSettlements(ListQueryRequest $request, MarketplaceOperationsReadService $service): StreamedResponse
    {
        $filters = $request->filters(['status', 'transaction_id', 'customer_id', 'date_from', 'date_to']);

        return response()->streamDownload(function () use ($service, $filters): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, [
                'transaction_code', 'status', 'product_code', 'buyer', 'seller',
                'deposit_amount', 'deposit_deduction_amount', 'refunded_amount',
                'released_amount', 'dispute_outcome', 'dispute_resolution', 'completed_at',
            ]);

            foreach ($service->rentalSettlementExportRows($filters) as $transaction) {
                $dispute = $transaction->disputes->sortByDesc('id')->first();
                fputcsv($stream, [
                    $transaction->code,
                    $transaction->status,
                    $transaction->product?->code,
                    $transaction->buyer?->name,
                    $transaction->seller?->name,
                    $transaction->deposit_amount,
                    $transaction->rental_deposit_deduction_amount,
                    $transaction->refunded_amount,
                    $transaction->released_amount,
                    $dispute?->outcome,
                    $dispute?->resolution,
                    optional($transaction->completed_at)->toISOString(),
                ]);
            }
            fclose($stream);
        }, 'rental-settlements-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function requestRentalSettlementExport(ListQueryRequest $request)
    {
        $export = MarketplaceExportRequest::query()->create([
            'type' => 'rental_settlement',
            'status' => 'pending',
            'filters' => $request->filters(['status', 'transaction_id', 'customer_id', 'date_from', 'date_to']),
            'requested_by' => user_id(),
        ]);

        BuildRentalSettlementExport::dispatch($export->id)->afterCommit();

        return ApiResponse::success($export, 'Đã đưa yêu cầu xuất dữ liệu vào hàng đợi.', 202);
    }

    public function rentalSettlementExportStatus(MarketplaceExportRequest $exportRequest)
    {
        abort_unless($exportRequest->type === 'rental_settlement', 404);

        return ApiResponse::success($exportRequest);
    }

    public function downloadRentalSettlementExport(MarketplaceExportRequest $exportRequest): BinaryFileResponse|Response
    {
        abort_unless($exportRequest->type === 'rental_settlement', 404);
        if ($exportRequest->status !== 'completed' || ! $exportRequest->file_path || ! Storage::disk('local')->exists($exportRequest->file_path)) {
            return response(['message' => 'Tệp xuất chưa sẵn sàng.'], 409);
        }
        if ($exportRequest->expires_at?->isPast()) {
            return response(['message' => 'Tệp xuất đã hết hạn.'], 410);
        }

        return response()->download(
            Storage::disk('local')->path($exportRequest->file_path),
            'rental-settlements-'.$exportRequest->id.'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    public function documentChecklist(Transaction $transaction, MarketplaceOperationsReadService $service)
    {
        return ApiResponse::success($service->documentChecklist($transaction));
    }
}
