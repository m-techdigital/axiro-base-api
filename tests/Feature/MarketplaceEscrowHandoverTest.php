<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\TransactionAssetSnapshot;
use App\Services\Marketplace\TransactionLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

class MarketplaceEscrowHandoverTest extends TestCase
{
    use CreatesMarketplaceFixtures;
    use RefreshDatabase;

    public function test_required_snapshot_blocks_seller_handover(): void
    {
        $buyer = Customer::factory()->create();
        $seller = Customer::factory()->create();
        $transaction = $this->createMarketplaceTransaction($buyer, $seller, [
            'status' => 'paid',
            'asset_delivery_method' => 'account_credentials',
            'inspection_period_minutes' => 45,
            'requires_pre_handover_snapshot' => true,
        ]);
        $transaction->product->update(['requires_pre_handover_snapshot' => true]);

        $service = app(TransactionLifecycleService::class);
        $this->assertNotContains('seller_handover', $service->allowedActions($transaction, $seller->id));

        $this->expectException(ValidationException::class);

        $service->transition(
            $transaction,
            'seller_handover',
            'customer',
            $seller->id,
            'Bàn giao qua kênh bảo mật của nền tảng.',
        );
    }

    public function test_handover_snapshots_delivery_policy_and_starts_inspection_window(): void
    {
        $buyer = Customer::factory()->create();
        $seller = Customer::factory()->create();
        $transaction = $this->createMarketplaceTransaction($buyer, $seller, [
            'status' => 'paid',
            'asset_delivery_method' => 'in_game_trade',
            'inspection_period_minutes' => 60,
            'requires_pre_handover_snapshot' => true,
        ]);
        $transaction->product->update([
            'product_type' => 'item',
            'delivery_method' => 'in_game_trade',
            'requires_pre_handover_snapshot' => true,
        ]);
        TransactionAssetSnapshot::query()->create([
            'transaction_id' => $transaction->id,
            'stage' => 'before_handover',
            'customer_id' => $seller->id,
            'actor_type' => 'customer',
            'actor_id' => $seller->id,
            'images' => ['/storage/test/item-before.png'],
            'note' => 'Vật phẩm còn nguyên trạng trước bàn giao.',
            'captured_at' => now(),
        ]);

        $service = app(TransactionLifecycleService::class);
        $this->assertContains('seller_handover', $service->allowedActions($transaction, $seller->id));

        $result = $service->transition(
            $transaction,
            'seller_handover',
            'customer',
            $seller->id,
            'Giao vật phẩm tại máy chủ đã công bố.',
        );

        $this->assertSame('handover_pending', $result->status);
        $this->assertSame('in_game_trade', $result->asset_delivery_method);
        $this->assertSame(60, $result->inspection_period_minutes);
        $this->assertNotNull($result->inspection_deadline_at);
        $this->assertSame('Giao vật phẩm tại máy chủ đã công bố.', $result->seller_delivery_note);
        $this->assertDatabaseHas('transaction_checkpoints', [
            'transaction_id' => $transaction->id,
            'checkpoint' => 'seller_handover',
            'actor_id' => $seller->id,
        ]);
    }
}
