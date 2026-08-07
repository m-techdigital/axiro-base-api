<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\EscrowBoxEvent;
use App\Services\Marketplace\EscrowBoxService;
use App\Services\Marketplace\EscrowBoxTimelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class EscrowBoxTimelineContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_timeline_uses_parent_contract_and_masks_identity(): void
    {
        [$partyA, $partyB, $box] = $this->claimedBox();

        EscrowBoxEvent::query()->create([
            'escrow_box_id' => $box->id,
            'event_type' => 'counterparty_invite_accepted',
            'actor_type' => 'customer',
            'actor_id' => $partyB->id,
            'actor_side' => 'party_b',
            'metadata' => ['agreement_version' => 1],
            'occurred_at' => now()->addSecond(),
        ]);

        $item = app(EscrowBoxTimelineService::class)
            ->list($box, [], 'customer', $partyA->id)
            ->items()[0];

        foreach (['activity_type', 'activity_subtype', 'metadata', 'changed_by', 'created_at'] as $key) {
            $this->assertArrayHasKey($key, $item);
        }
        $this->assertSame('Bên B', $item['actor']['name']);
        $this->assertNull($item['actor']['id']);
        $this->assertStringNotContainsString('Người B', json_encode($item));
    }

    public function test_timeline_filters_and_paginates_at_query_boundary(): void
    {
        [$partyA, $partyB, $box] = $this->claimedBox();

        foreach (range(1, 12) as $index) {
            EscrowBoxEvent::query()->create([
                'escrow_box_id' => $box->id,
                'event_type' => $index % 2 === 0 ? 'terms_updated' : 'counterparty_invite_accepted',
                'actor_type' => 'customer',
                'actor_id' => $index % 2 === 0 ? $partyA->id : $partyB->id,
                'actor_side' => $index % 2 === 0 ? 'party_a' : 'party_b',
                'metadata' => ['agreement_version' => 1],
                'occurred_at' => now()->addSeconds($index),
            ]);
        }

        $page = app(EscrowBoxTimelineService::class)->list($box, [
            'activity_type' => 'updated',
            'page' => 2,
            'per_page' => 2,
        ]);

        $this->assertSame(6, $page->total());
        $this->assertSame(2, $page->currentPage());
        $this->assertSame(2, $page->perPage());
        $this->assertCount(2, $page->items());
        $this->assertSame(['updated'], array_values(array_unique(array_column($page->items(), 'activity_type'))));
    }

    public function test_non_participant_cannot_read_customer_timeline(): void
    {
        [, , $box] = $this->claimedBox();
        $outsider = Customer::factory()->create();

        $this->expectException(NotFoundHttpException::class);

        app(EscrowBoxTimelineService::class)->list($box, [], 'customer', $outsider->id);
    }

    private function claimedBox(): array
    {
        $partyA = Customer::factory()->create(['name' => 'Người A']);
        $partyB = Customer::factory()->create(['name' => 'Người B']);
        $created = app(EscrowBoxService::class)->create($partyA->id, [
            'deal_type' => 'exchange',
            'party_a_asset' => [
                'type' => 'game_account',
                'title' => 'Tài khoản A',
                'description' => 'Tài sản A',
                'reference_value' => 100000,
                'delivery_method' => 'email_transfer',
            ],
            'party_b_asset' => [
                'type' => 'item',
                'title' => 'Vật phẩm B',
                'description' => 'Tài sản B',
                'reference_value' => 100000,
                'delivery_method' => 'in_game_trade',
            ],
            'fee_payer_mode' => 'split_equal',
            'inspection_period_minutes' => 60,
            'success_conditions' => 'Hai bên nhận đúng tài sản.',
            'cancellation_conditions' => 'Admin xác minh giao dịch không thể tiếp tục.',
            'expires_in_hours' => 72,
        ]);
        $box = app(EscrowBoxService::class)->claim($created['invite_token'], $partyB->id);

        return [$partyA, $partyB, $box];
    }
}
