<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Services\Marketplace\EscrowBoxPresenter;
use App\Services\Marketplace\EscrowBoxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class EscrowBoxWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return [
            'deal_type' => 'exchange_with_topup',
            'party_a_asset' => [
                'type' => 'game_account',
                'title' => 'Tài khoản game Bên A',
                'description' => 'Rank cao, thông tin bàn giao qua Admin.',
                'reference_value' => 2000000,
                'delivery_method' => 'email_transfer',
            ],
            'party_b_asset' => [
                'type' => 'item',
                'title' => 'Vật phẩm Bên B',
                'description' => 'Vật phẩm được giao trực tiếp trong game.',
                'reference_value' => 1500000,
                'delivery_method' => 'in_game_trade',
            ],
            'topup_payer_side' => 'party_b',
            'topup_amount' => 500000,
            'fee_payer_mode' => 'party_b',
            'inspection_period_minutes' => 60,
            'success_conditions' => 'Hai bên nhận đúng tài sản theo mô tả.',
            'cancellation_conditions' => 'Hủy khi Admin xác minh tài sản không đúng.',
            'additional_terms' => 'Không chia sẻ thông tin liên hệ trực tiếp.',
            'expires_in_hours' => 72,
        ];
    }

    public function test_invite_is_one_time_and_customer_presenter_never_exposes_identity(): void
    {
        $partyA = Customer::factory()->create(['username' => 'party-a-secret']);
        $partyB = Customer::factory()->create(['username' => 'party-b-secret']);
        $outsider = Customer::factory()->create();
        $service = app(EscrowBoxService::class);
        $presenter = app(EscrowBoxPresenter::class);

        $created = $service->create($partyA->id, $this->payload());
        $token = $created['invite_token'];
        $preview = $presenter->invitePreview($service->preview($token));
        $this->assertSame('Bên A', $preview['creator_label']);
        $this->assertArrayNotHasKey('party_a_customer_id', $preview);
        $this->assertStringNotContainsString('party-a-secret', json_encode($preview));

        $claimed = $service->claim($token, $partyB->id);
        $customerView = $presenter->customer($claimed, $partyB->id);
        $this->assertSame('party_b', $customerView['self_role']);
        $this->assertSame('Bên A', $customerView['counterparty_label']);
        $this->assertStringNotContainsString('party-a-secret', json_encode($customerView));
        $this->assertStringNotContainsString('party-b-secret', json_encode($customerView));

        $this->expectException(NotFoundHttpException::class);
        $service->preview($token);
        $this->actingAs($outsider, 'customer_api');
    }

    public function test_agreement_admin_review_fee_snapshot_and_payment_obligations(): void
    {
        $partyA = Customer::factory()->create();
        $partyB = Customer::factory()->create();
        $admin = User::factory()->create();
        $service = app(EscrowBoxService::class);

        $created = $service->create($partyA->id, $this->payload());
        $box = $service->claim($created['invite_token'], $partyB->id);
        $box = $service->confirm($box, $partyB->id, $box->expected_version);
        $this->assertSame('admin_review', $box->status);

        $box = $service->adminReview($box, $admin->id, [
            'action' => 'approve',
            'expected_version' => $box->expected_version,
            'risk_level' => 'low',
            'review_note' => 'Đủ bằng chứng để mở thanh toán.',
            'handover_sequence' => 'party_a_first',
        ]);

        $this->assertSame('payment_pending', $box->status);
        $this->assertSame('100000.00', $box->final_fee);
        $this->assertNotNull($box->transaction_id);
        $this->assertSame('escrow_box', $box->transaction->initiation_source);
        $this->assertCount(2, $box->obligations);
        $this->assertDatabaseHas('escrow_box_payment_obligations', [
            'escrow_box_id' => $box->id,
            'party_side' => 'party_b',
            'type' => 'topup',
            'amount' => '500000.00',
        ]);
        $this->assertDatabaseHas('escrow_box_payment_obligations', [
            'escrow_box_id' => $box->id,
            'party_side' => 'party_b',
            'type' => 'platform_fee',
            'amount' => '100000.00',
        ]);
        $this->assertDatabaseHas('escrow_box_handover_steps', [
            'escrow_box_id' => $box->id,
            'party_side' => 'party_a',
            'status' => 'ready',
        ]);
        $this->assertDatabaseHas('escrow_box_handover_steps', [
            'escrow_box_id' => $box->id,
            'party_side' => 'party_b',
            'status' => 'blocked',
        ]);
    }
}
