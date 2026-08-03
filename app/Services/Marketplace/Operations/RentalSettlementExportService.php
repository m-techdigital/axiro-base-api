<?php

namespace App\Services\Marketplace\Operations;

use App\Jobs\BuildRentalSettlementExport;
use App\Models\MarketplaceExportRequest;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RentalSettlementExportService
{
    public const FILTERS = ['status', 'transaction_id', 'customer_id', 'date_from', 'date_to'];

    public function stream(array $filters, MarketplaceOperationsReadService $readService): StreamedResponse
    {
        return response()->streamDownload(function () use ($readService, $filters): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, [
                'transaction_code', 'status', 'product_code', 'buyer', 'seller',
                'deposit_amount', 'deposit_deduction_amount', 'refunded_amount',
                'released_amount', 'dispute_outcome', 'dispute_resolution', 'completed_at',
            ]);

            foreach ($readService->rentalSettlementExportRows($filters) as $transaction) {
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

    public function request(array $filters): MarketplaceExportRequest
    {
        $export = MarketplaceExportRequest::query()->create([
            'type' => 'rental_settlement',
            'status' => 'pending',
            'filters' => $filters,
            'requested_by' => user_id(),
        ]);

        BuildRentalSettlementExport::dispatch($export->id)->afterCommit();

        return $export;
    }

    public function assertRentalSettlement(MarketplaceExportRequest $export): void
    {
        abort_unless($export->type === 'rental_settlement', 404);
    }

    public function download(MarketplaceExportRequest $export): BinaryFileResponse|Response
    {
        $this->assertRentalSettlement($export);
        if ($export->status !== 'completed' || ! $export->file_path || ! Storage::disk('local')->exists($export->file_path)) {
            return response(['message' => 'Tệp xuất chưa sẵn sàng.'], 409);
        }
        if ($export->expires_at?->isPast()) {
            return response(['message' => 'Tệp xuất đã hết hạn.'], 410);
        }

        return response()->download(
            Storage::disk('local')->path($export->file_path),
            'rental-settlements-'.$export->id.'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
