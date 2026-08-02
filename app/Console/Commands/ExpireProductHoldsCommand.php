<?php

namespace App\Console\Commands;

use App\Services\ProductAvailabilityService;
use Illuminate\Console\Command;

class ExpireProductHoldsCommand extends Command
{
    protected $signature = 'marketplace:expire-product-holds {--limit=200}';

    protected $description = 'Nhả các lượt giữ chỗ sản phẩm đã hết hạn theo source ownership.';

    public function handle(ProductAvailabilityService $service): int
    {
        $count = $service->expireHolds(max(1, (int) $this->option('limit')));
        $this->info("Expired product holds: {$count}");

        return self::SUCCESS;
    }
}
