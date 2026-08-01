<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MarketplaceIntegrityCommand extends Command
{
    protected $signature = 'marketplace:integrity {--json : Output machine-readable JSON}';

    protected $description = 'Check canonical marketplace financial and ownership invariants.';

    public function handle(): int
    {
        $checks = [
            $this->countCheck('wallet_negative', 'Ví có số dư âm', 'customer_wallets', fn () => DB::table('customer_wallets')->where('available_balance', '<', 0)->orWhere('held_balance', '<', 0)->count()),
            $this->countCheck('wallet_lifetime_negative', 'Tổng vòng đời ví âm', 'customer_wallets', fn () => DB::table('customer_wallets')->where('lifetime_credit', '<', 0)->orWhere('lifetime_debit', '<', 0)->count()),
            $this->countCheck('transaction_amount_negative', 'Giao dịch có giá trị âm', 'transactions', fn () => DB::table('transactions')->where(function ($query): void {
                $query->where('transaction_value', '<', 0)
                    ->orWhere('service_fee', '<', 0)
                    ->orWhere('discount', '<', 0)
                    ->orWhere('deposit_amount', '<', 0)
                    ->orWhere('total_payable', '<', 0)
                    ->orWhere('paid_amount', '<', 0)
                    ->orWhere('refunded_amount', '<', 0)
                    ->orWhere('escrow_amount', '<', 0)
                    ->orWhere('released_amount', '<', 0);
            })->count()),
            $this->countCheck('transaction_total_mismatch', 'Tổng phải trả sai công thức', 'transactions', fn () => DB::table('transactions')
                ->whereRaw('ABS(total_payable - (transaction_value + service_fee + buyer_fee_amount + tax_amount + deposit_amount - discount)) > 0.01')
                ->count(), ['buyer_fee_amount', 'tax_amount']),
            $this->countCheck('seller_net_mismatch', 'Tiền ròng người bán sai', 'transactions', fn () => DB::table('transactions')
                ->whereRaw('ABS(COALESCE(seller_net_amount, 0) - CASE WHEN (COALESCE(transaction_value, 0) - COALESCE(seller_fee_amount, 0)) < 0 THEN 0 ELSE (COALESCE(transaction_value, 0) - COALESCE(seller_fee_amount, 0)) END) > 0.01')
                ->count(), ['seller_net_amount', 'seller_fee_amount']),
            $this->countCheck('withdrawal_net_mismatch', 'Số tiền rút ròng sai', 'withdrawal_requests', fn () => DB::table('withdrawal_requests')
                ->whereRaw('ABS(COALESCE(net_amount, 0) - CASE WHEN (COALESCE(amount, 0) - COALESCE(fee_amount, 0)) < 0 THEN 0 ELSE (COALESCE(amount, 0) - COALESCE(fee_amount, 0)) END) > 0.01')
                ->count()),
            $this->countCheck('multiple_default_payout_accounts', 'Khách hàng có nhiều tài khoản nhận tiền mặc định', 'customer_payout_accounts', fn () => DB::table('customer_payout_accounts')
                ->select('customer_id')
                ->where('is_default', true)
                ->groupBy('customer_id')
                ->havingRaw('COUNT(*) > 1')
                ->get()
                ->count()),
            $this->countCheck('published_product_without_offer', 'Sản phẩm đã xuất bản nhưng không có mục đích giao dịch', 'products', fn () => DB::table('products')->where('is_published', true)->whereNotExists(fn ($q) => $q->selectRaw('1')->from('model_offer_modes')->whereColumn('model_offer_modes.model_id', 'products.id')->where('model_offer_modes.model_type', Product::class))->count()),
        ];

        $failures = collect($checks)->where('count', '>', 0)->values();
        $payload = [
            'ok' => $failures->isEmpty(),
            'checked_at' => now()->toIso8601String(),
            'checks' => $checks,
            'failure_count' => $failures->sum('count'),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $rows = collect($checks)->map(fn (array $check): array => [
                $check['code'],
                $check['status'],
                $check['count'],
                $check['label'],
            ])->all();
            $this->table(['Mã', 'Trạng thái', 'Số lỗi', 'Kiểm tra'], $rows);
            $payload['ok'] ? $this->info('Marketplace integrity: PASS') : $this->error('Marketplace integrity: FAIL');
        }

        return $payload['ok'] ? self::SUCCESS : self::FAILURE;
    }

    private function countCheck(string $code, string $label, string $table, callable $resolver, array $columns = [], array $extraTables = []): array
    {
        $tables = array_merge([$table], $extraTables);
        foreach ($tables as $requiredTable) {
            if (! Schema::hasTable($requiredTable)) {
                return ['code' => $code, 'label' => $label, 'status' => 'skipped', 'count' => 0, 'reason' => "missing_table:{$requiredTable}"];
            }
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return ['code' => $code, 'label' => $label, 'status' => 'skipped', 'count' => 0, 'reason' => "missing_column:{$table}.{$column}"];
            }
        }

        $count = (int) $resolver();

        return ['code' => $code, 'label' => $label, 'status' => $count === 0 ? 'pass' : 'fail', 'count' => $count];
    }
}
