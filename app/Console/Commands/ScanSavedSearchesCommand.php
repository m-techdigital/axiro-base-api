<?php

namespace App\Console\Commands;

use App\Enums\ProductSelectionContext;
use App\Models\Product;
use App\Models\SavedSearch;
use App\Services\Marketplace\MarketplaceNotificationService;
use App\Services\ProductSelectionService;
use Illuminate\Console\Command;

class ScanSavedSearchesCommand extends Command
{
    protected $signature = 'marketplace:scan-saved-searches';

    protected $description = 'Notify customers when new products match saved searches';

    public function handle(MarketplaceNotificationService $notifications, ProductSelectionService $selection): int
    {
        SavedSearch::query()->where('notify', true)->chunkById(100, function ($searches) use ($notifications, $selection) {
            foreach ($searches as $search) {
                $filters = $search->filters ?? [];
                $mode = $filters['offer_mode'] ?? $filters['transaction_type'] ?? null;
                $mode = match ($mode) {
                    'sale', 'purchase' => 'sell', 'rental' => 'rent', 'installment' => 'sell', default => $mode
                };
                $query = $selection->apply(Product::query()->with('offerModes'), ProductSelectionContext::PUBLIC_MARKETPLACE, $mode)
                    ->when($search->last_notified_at, fn ($q, $value) => $q->where('published_at', '>', $value));
                if (($filters['transaction_type'] ?? null) === 'installment') {
                    $query->where('installment_enabled', true);
                }
                if (! empty($filters['product_type'])) {
                    $query->where('product_type', $filters['product_type']);
                }
                if (! empty($filters['game_code'])) {
                    $query->where('game_code', $filters['game_code']);
                }
                if (! empty($filters['keyword'])) {
                    $keyword = $filters['keyword'];
                    $query->where(fn ($q) => $q->where('name', 'like', "%{$keyword}%")->orWhere('description', 'like', "%{$keyword}%"));
                }
                $matches = $query->latest('published_at')->limit(5)->get();
                if ($matches->isNotEmpty()) {
                    $notifications->send($search->customer_id, 'product_saved_search_match', 'Có sản phẩm phù hợp', "Có {$matches->count()} sản phẩm mới phù hợp với bộ lọc {$search->name}.", '/account/trust', ['saved_search_id' => $search->id, 'product_ids' => $matches->pluck('id')->all()]);
                }
                $search->update(['last_notified_at' => now()]);
            }
        });

        return self::SUCCESS;
    }
}
