<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Services\Marketplace\MarketplaceNotificationService;
use Illuminate\Console\Command;

class ScanMarketplaceDueCommand extends Command
{
    protected $signature = 'marketplace:scan-due {--dry-run}';

    protected $description = 'Đánh dấu kỳ thanh toán và giao dịch thuê quá hạn.';

    public function handle(MarketplaceNotificationService $notifications): int
    {
        $dry = (bool) $this->option('dry-run');
        $paymentCount = 0;
        $rentalCount = 0;
        TransactionPayment::with('transaction')->whereIn('status', ['pending', 'rejected'])->whereDate('due_date', '<', today())->chunkById(100, function ($items) use (&$paymentCount, $dry, $notifications) {
            foreach ($items as $payment) {
                $paymentCount++;
                if (! $dry) {
                    $payment->update(['status' => 'overdue']);
                    $payment->transaction?->update(['status' => 'overdue', 'next_payment_due_at' => $payment->due_date]);
                    if ($payment->transaction) {
                        $notifications->transaction($payment->customer_id, 'payment_overdue', 'Khoản thanh toán đã quá hạn', 'Khoản '.$payment->code.' đã quá hạn. Vui lòng thanh toán hoặc liên hệ hỗ trợ.', $payment->transaction_id, $payment->transaction->code);
                    }
                }
            }
        });
        Transaction::where('transaction_type', 'rental')->whereIn('status', ['active', 'paid', 'handover_pending'])->whereNotNull('rental_end_at')->where('rental_end_at', '<', now())->chunkById(100, function ($items) use (&$rentalCount, $dry, $notifications) {
            foreach ($items as $transaction) {
                $rentalCount++;
                if (! $dry) {
                    $transaction->update(['status' => 'overdue']);
                    foreach ([$transaction->buyer_customer_id, $transaction->seller_customer_id] as $id) {
                        $notifications->transaction($id, 'rental_overdue', 'Giao dịch thuê đã quá hạn', 'Giao dịch '.$transaction->code.' đã vượt thời điểm hoàn trả.', $transaction->id, $transaction->code);
                    }
                }
            }
        });
        $this->info("Kỳ thanh toán quá hạn: $paymentCount; giao dịch thuê quá hạn: $rentalCount".($dry ? ' (chỉ kiểm tra)' : ''));

        return self::SUCCESS;
    }
}
