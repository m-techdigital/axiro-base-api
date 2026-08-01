<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class PruneAuditLogs extends Command
{
    protected $signature = 'audit:prune {--dry-run : Chỉ thống kê, không xóa dữ liệu}';

    protected $description = 'Xóa nhật ký đã hết thời hạn lưu giữ theo cấu hình';

    public function handle(): int
    {
        $generalCutoff = now()->subDays(max(1, (int) config('audit.retention_days', 365)));
        $validationCutoff = now()->subDays(max(1, (int) config('audit.validation_retention_days', 90)));

        $general = AuditLog::query()->where('audit_type', '!=', 'validation')->where('created_at', '<', $generalCutoff);
        $validation = AuditLog::query()->where('audit_type', 'validation')->where('created_at', '<', $validationCutoff);
        $count = (clone $general)->count() + (clone $validation)->count();

        if ($this->option('dry-run')) {
            $this->info("Có {$count} nhật ký đủ điều kiện xóa.");

            return self::SUCCESS;
        }

        $deleted = $general->delete() + $validation->delete();
        $this->info("Đã xóa {$deleted} nhật ký hết thời hạn lưu giữ.");

        return self::SUCCESS;
    }
}
