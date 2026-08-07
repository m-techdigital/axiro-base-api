<?php

namespace Tests\Feature;

use App\Http\Requests\Customer\EscrowBoxCreateRequest;
use App\Models\Customer;
use App\Models\User;
use App\Services\Marketplace\EscrowBoxPresenter;
use App\Services\Marketplace\EscrowBoxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
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
        $preview = $presenter->invitePreview($service->preview($token, $partyB->id));
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
        $service->preview($token, $outsider->id);
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

    public function test_admin_can_assign_two_customers_and_each_private_link_accepts_only_the_assigned_party(): void
    {
        $partyA = Customer::factory()->create(['status' => 'active']);
        $partyB = Customer::factory()->create(['status' => 'active']);
        $outsider = Customer::factory()->create(['status' => 'active']);
        $admin = User::factory()->create();
        $service = app(EscrowBoxService::class);
        $presenter = app(EscrowBoxPresenter::class);

        $created = $service->createByAdmin($admin->id, [
            ...$this->payload(),
            'party_a_customer_id' => $partyA->id,
            'party_b_customer_id' => $partyB->id,
        ]);
        $box = $created['box'];
        $this->assertSame('admin_assigned', $box->initiation_source);
        $this->assertSame('awaiting_party_acceptance', $box->status);
        $this->assertNull($box->party_a_confirmed_at);
        $this->assertNull($box->party_b_confirmed_at);

        try {
            $service->previewAssignedInvite($created['party_a_invite_token'], $outsider->id);
            $this->fail('Outsider must not preview an assigned invitation.');
        } catch (NotFoundHttpException) {
            $this->assertTrue(true);
        }

        $previewA = $service->previewAssignedInvite($created['party_a_invite_token'], $partyA->id);
        $this->assertSame('party_a', $previewA['party_side']);
        $this->assertSame('Bên A', $presenter->assignedInvitePreview($previewA['box'], 'party_a')['self_label']);

        $box = $service->acceptAssignedInvite($created['party_a_invite_token'], $partyA->id);
        $this->assertSame('awaiting_party_acceptance', $box->status);
        $this->assertNotNull($box->party_a_invite_accepted_at);
        $this->assertNull($box->party_b_invite_accepted_at);

        $box = $service->acceptAssignedInvite($created['party_b_invite_token'], $partyB->id);
        $this->assertSame('admin_review', $box->status);
        $this->assertNotNull($box->party_b_invite_accepted_at);
        $this->assertSame($box->agreement_version, $box->party_a_confirmed_version);
        $this->assertSame($box->agreement_version, $box->party_b_confirmed_version);
        $this->assertDatabaseHas('escrow_boxes', [
            'id' => $box->id,
            'party_a_invite_token_hash' => null,
            'party_b_invite_token_hash' => null,
        ]);
    }

    public function test_creator_cannot_preview_own_link_and_can_rotate_cancel_then_clone(): void
    {
        $creator = Customer::factory()->create();
        $counterparty = Customer::factory()->create();
        $service = app(EscrowBoxService::class);

        $created = $service->create($creator->id, $this->payload());
        $box = $created['box'];

        try {
            $service->preview($created['invite_token'], $creator->id);
            $this->fail('Creator must not preview the public counterparty invitation.');
        } catch (NotFoundHttpException) {
            $this->assertTrue(true);
        }

        $rotated = $service->rotateCustomerInvite($box, $creator->id, $box->expected_version);
        $this->assertNotSame($created['invite_token'], $rotated['invite_token']);

        try {
            $service->preview($created['invite_token'], $counterparty->id);
            $this->fail('Old invitation must be invalid after rotation.');
        } catch (NotFoundHttpException) {
            $this->assertTrue(true);
        }

        $this->assertSame($box->code, $service->preview($rotated['invite_token'], $counterparty->id)->code);
        $cancelled = $service->cancel($rotated['box'], $creator->id, $rotated['box']->expected_version);
        $this->assertSame('cancelled', $cancelled->status);

        $cloned = $service->cloneCancelled($cancelled, $creator->id);
        $this->assertNotSame($cancelled->id, $cloned['box']->id);
        $this->assertSame('awaiting_counterparty', $cloned['box']->status);
        $this->assertSame($cancelled->agreement_terms, $cloned['box']->agreement_terms);
    }

    public function test_counterparty_cannot_cancel_customer_created_box(): void
    {
        $creator = Customer::factory()->create();
        $counterparty = Customer::factory()->create();
        $service = app(EscrowBoxService::class);
        $created = $service->create($creator->id, $this->payload());
        $box = $service->claim($created['invite_token'], $counterparty->id);

        $this->expectException(HttpException::class);
        $service->cancel($box, $counterparty->id, $box->expected_version);
    }

    public function test_creator_can_invite_counterparty_by_phone_cancel_and_replace_before_acceptance(): void
    {
        $creator = Customer::factory()->create(['phone' => '0909000001']);
        $first = Customer::factory()->create(['phone' => '0909000002']);
        $replacement = Customer::factory()->create(['phone' => '0909000003']);
        $service = app(EscrowBoxService::class);
        $created = $service->create($creator->id, $this->payload());

        $resolved = $service->resolveCounterpartyByPhone(
            $created['box'],
            $creator->id,
            $created['box']->expected_version,
            '0909 000 002',
        );
        $this->assertSame('••••0002', $resolved['candidate']['phone_hint']);

        $invited = $service->inviteCounterpartyCandidate(
            $created['box'],
            $creator->id,
            $created['box']->expected_version,
            $resolved['candidate_token'],
        );
        $this->assertSame('awaiting_party_acceptance', $invited->status);
        $this->assertSame($first->id, $invited->party_b_customer_id);
        $this->assertSame('customer_phone_invite', $invited->initiation_source);

        $cancelled = $service->cancelCounterpartyInvite(
            $invited,
            $creator->id,
            $invited->expected_version,
        );
        $this->assertSame('awaiting_counterparty', $cancelled->status);
        $this->assertNull($cancelled->party_b_customer_id);

        $replacementCandidate = $service->resolveCounterpartyByPhone(
            $cancelled,
            $creator->id,
            $cancelled->expected_version,
            $replacement->phone,
        );
        $reassigned = $service->inviteCounterpartyCandidate(
            $cancelled,
            $creator->id,
            $cancelled->expected_version,
            $replacementCandidate['candidate_token'],
        );
        $accepted = $service->acceptCounterpartyInvite(
            $reassigned,
            $replacement->id,
            $reassigned->expected_version,
        );
        $this->assertSame('terms_pending', $accepted->status);
        $this->assertNotNull($accepted->party_b_invite_accepted_at);

        $this->expectException(ValidationException::class);
        $service->cancelCounterpartyInvite(
            $accepted,
            $creator->id,
            $accepted->expected_version,
        );
    }

    public function test_horizontal_exchange_validation_excludes_topup_fields(): void
    {
        $payload = $this->payload();
        $payload['deal_type'] = 'exchange';
        unset($payload['topup_amount'], $payload['topup_payer_side']);

        $validator = Validator::make(
            $payload,
            app(EscrowBoxCreateRequest::class)->rules(),
            app(EscrowBoxCreateRequest::class)->messages(),
            app(EscrowBoxCreateRequest::class)->attributes(),
        );

        $this->assertTrue($validator->passes());
        $this->assertArrayNotHasKey('topup_amount', $validator->errors()->toArray());
    }

    public function test_topup_exchange_maps_minimum_error_to_topup_field(): void
    {
        $payload = $this->payload();
        $payload['topup_amount'] = 999;
        $request = app(EscrowBoxCreateRequest::class);
        $validator = Validator::make(
            $payload,
            $request->rules(),
            $request->messages(),
            $request->attributes(),
        );

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'Số tiền bù phải tối thiểu 1.000 đ.',
            $validator->errors()->first('topup_amount'),
        );
    }

    public function test_phone_invite_accepts_country_code_variant_and_cancel_notifies_previous_invitee(): void
    {
        $creator = Customer::factory()->create(['phone' => '0909000011']);
        $counterparty = Customer::factory()->create(['phone' => '+84 909 000 012']);
        $service = app(EscrowBoxService::class);
        $created = $service->create($creator->id, $this->payload());

        $resolved = $service->resolveCounterpartyByPhone(
            $created['box'],
            $creator->id,
            $created['box']->expected_version,
            '84 909 000 012',
        );
        $invited = $service->inviteCounterpartyCandidate(
            $created['box'],
            $creator->id,
            $created['box']->expected_version,
            $resolved['candidate_token'],
        );
        $this->assertSame($counterparty->id, $invited->party_b_customer_id);

        $cancelled = $service->cancelCounterpartyInvite(
            $invited,
            $creator->id,
            $invited->expected_version,
        );
        $this->assertNull($cancelled->party_b_customer_id);
        $this->assertDatabaseHas('marketplace_notifications', [
            'customer_id' => $counterparty->id,
            'type' => 'escrow_box_invite_cancelled',
        ]);
    }

    public function test_counterparty_candidate_token_is_bound_to_box_and_creator(): void
    {
        $creator = Customer::factory()->create(['phone' => '0909000021']);
        $otherCreator = Customer::factory()->create(['phone' => '0909000022']);
        $counterparty = Customer::factory()->create(['phone' => '0909000023']);
        $service = app(EscrowBoxService::class);
        $first = $service->create($creator->id, $this->payload());
        $second = $service->create($otherCreator->id, $this->payload());
        $resolved = $service->resolveCounterpartyByPhone(
            $first['box'],
            $creator->id,
            $first['box']->expected_version,
            $counterparty->phone,
        );

        $this->expectException(ValidationException::class);
        $service->inviteCounterpartyCandidate(
            $second['box'],
            $otherCreator->id,
            $second['box']->expected_version,
            $resolved['candidate_token'],
        );
    }

    public function test_creator_can_update_box_while_counterparty_invitation_is_pending(): void
    {
        $creator = Customer::factory()->create();
        $counterparty = Customer::factory()->create(['phone' => '0909123456']);
        $service = app(EscrowBoxService::class);
        $created = $service->create($creator->id, $this->payload());
        $resolved = $service->resolveCounterpartyByPhone(
            $created['box'],
            $creator->id,
            $created['box']->expected_version,
            $counterparty->phone,
        );
        $invited = $service->inviteCounterpartyCandidate(
            $created['box'],
            $creator->id,
            $created['box']->expected_version,
            $resolved['candidate_token'],
        );

        $payload = $this->payload();
        $payload['expected_version'] = $invited->expected_version;
        $payload['party_a_asset']['title'] = 'Tài sản đã cập nhật';
        $updated = $service->updateTerms($invited, $creator->id, $payload);

        $this->assertSame('awaiting_party_acceptance', $updated->status);
        $this->assertSame('Tài sản đã cập nhật', $updated->agreement_terms['party_a_asset']['title']);
        $this->assertSame($counterparty->id, $updated->party_b_customer_id);
    }
}
