<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\TransactionEvent;
use App\Models\WalletTransaction;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceDemoSeederIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seed_respects_customer_wallet_and_media_contracts(): void
    {
        $this->seed(DatabaseSeeder::class);

        $pending = WalletTransaction::query()->where('code', 'NAP-DEMO-PENDING')->firstOrFail();
        $this->assertSame('deposit_request', $pending->type);
        $this->assertSame('submitted', $pending->status);
        $this->assertSame($pending->available_before, $pending->available_after);

        $item = Product::query()->where('code', 'ITEM-0901')->firstOrFail();
        $this->assertSame('item', $item->product_type);
        $this->assertSame('in_game_trade', $item->delivery_method);
        $this->assertTrue($item->requires_pre_handover_snapshot);

        Product::query()->where('code', 'like', '%-%')->each(function (Product $product): void {
            $urls = array_values(array_filter($product->image_urls ?? []));
            $this->assertSame($urls, array_values(array_unique($urls)), 'Demo product image_urls must not contain duplicates.');
        });

        $cancelled = TransactionEvent::query()->where('event_type', 'cancelled')->first();
        if ($cancelled) {
            $this->assertNotSame('customer', $cancelled->actor_type, 'Demo data must not imply customer self-cancellation.');
        }
    }
}
