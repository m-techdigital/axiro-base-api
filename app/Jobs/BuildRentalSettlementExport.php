<?php

namespace App\Jobs;

use App\Models\MarketplaceExportRequest;
use App\Services\Marketplace\Operations\MarketplaceOperationsReadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BuildRentalSettlementExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $exportRequestId) {}

    public function handle(MarketplaceOperationsReadService $service): void
    {
        $export = MarketplaceExportRequest::query()->findOrFail($this->exportRequestId);
        if ($export->status === 'completed') {
            return;
        }

        $export->update(['status' => 'processing', 'started_at' => now(), 'error_message' => null]);
        $path = 'exports/rental-settlements-'.$export->id.'.csv';
        Storage::disk('local')->makeDirectory('exports');
        $stream = fopen(Storage::disk('local')->path($path), 'w');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, [
            'transaction_code', 'status', 'product_code', 'buyer', 'seller',
            'deposit_amount', 'deposit_deduction_amount', 'refunded_amount',
            'released_amount', 'dispute_outcome', 'dispute_resolution', 'completed_at',
        ]);

        $count = 0;
        $service->chunkRentalSettlementExportRows($export->filters ?? [], function ($transactions) use ($stream, &$count): void {
            foreach ($transactions as $transaction) {
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
                $count++;
            }
        });
        fclose($stream);

        $export->update([
            'status' => 'completed',
            'file_path' => $path,
            'row_count' => $count,
            'completed_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        MarketplaceExportRequest::query()->whereKey($this->exportRequestId)->update([
            'status' => 'failed',
            'error_message' => mb_substr($exception->getMessage(), 0, 2000),
        ]);
    }
}
