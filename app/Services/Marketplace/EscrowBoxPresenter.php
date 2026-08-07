<?php

namespace App\Services\Marketplace;

use App\Models\EscrowBox;

class EscrowBoxPresenter
{
    public function customer(EscrowBox $box, int $customerId): array
    {
        $side = $this->side($box, $customerId);
        abort_unless($side !== null, 404);
        $counterpartySide = $side === 'party_a' ? 'party_b' : 'party_a';

        return [
            'id' => $box->id,
            'code' => $box->code,
            'status' => $box->status,
            'deal_type' => $box->deal_type,
            'agreement_version' => $box->agreement_version,
            'expected_version' => $box->expected_version,
            'agreement_terms' => $box->agreement_terms,
            'self_role' => $side,
            'self_label' => $side === 'party_a' ? 'Bên A' : 'Bên B',
            'counterparty_label' => $counterpartySide === 'party_a' ? 'Bên A' : 'Bên B',
            'counterparty_joined' => $box->party_b_customer_id !== null,
            'is_creator' => (int) $box->created_by_customer_id === $customerId,
            'can_cancel' => (int) $box->created_by_customer_id === $customerId && in_array($box->status, ['awaiting_counterparty', 'terms_pending', 'changes_requested', 'admin_review', 'awaiting_party_acceptance'], true),
            'can_update' => ((int) $box->created_by_customer_id === $customerId && in_array($box->status, ['awaiting_counterparty', 'awaiting_party_acceptance'], true)) || ($side !== null && in_array($box->status, ['terms_pending', 'changes_requested'], true)),
            'can_rotate_invite' => (int) $box->created_by_customer_id === $customerId && $box->status === 'awaiting_counterparty' && $box->party_b_customer_id === null,
            'can_invite_counterparty_by_phone' => (int) $box->created_by_customer_id === $customerId && $box->status === 'awaiting_counterparty' && $box->party_b_customer_id === null,
            'can_cancel_counterparty_invite' => (int) $box->created_by_customer_id === $customerId && $box->initiation_source === 'customer_phone_invite' && $box->status === 'awaiting_party_acceptance' && $box->party_b_invite_accepted_at === null,
            'can_accept_counterparty_invite' => $side === 'party_b' && $box->initiation_source === 'customer_phone_invite' && $box->status === 'awaiting_party_acceptance' && $box->party_b_invite_accepted_at === null,
            'counterparty_invitation_pending' => $box->initiation_source === 'customer_phone_invite' && $box->status === 'awaiting_party_acceptance' && $box->party_b_invite_accepted_at === null,
            'can_clone' => (int) $box->created_by_customer_id === $customerId && in_array($box->status, ['cancelled', 'rejected', 'expired'], true),
            'self_confirmed' => $side === 'party_a'
                ? $box->party_a_confirmed_version === $box->agreement_version
                : $box->party_b_confirmed_version === $box->agreement_version,
            'counterparty_confirmed' => $counterpartySide === 'party_a'
                ? $box->party_a_confirmed_version === $box->agreement_version
                : $box->party_b_confirmed_version === $box->agreement_version,
            'topup_payer_side' => $box->topup_payer_side,
            'topup_amount' => $box->topup_amount,
            'fee_payer_mode' => $box->fee_payer_mode,
            'party_a_fee_amount' => $box->party_a_fee_amount,
            'party_b_fee_amount' => $box->party_b_fee_amount,
            'final_fee' => $box->final_fee,
            'risk_level' => $box->risk_level,
            'admin_review_note' => in_array($box->status, ['terms_pending', 'changes_requested', 'rejected'], true) ? $box->admin_review_note : null,
            'handover_sequence' => $box->handover_sequence,
            'inspection_period_minutes' => $box->inspection_period_minutes,
            'inspection_started_at' => $box->inspection_started_at,
            'inspection_deadline_at' => $box->inspection_deadline_at,
            'self_received' => $side === 'party_a' ? $box->party_a_received_at !== null : $box->party_b_received_at !== null,
            'counterparty_received' => $counterpartySide === 'party_a' ? $box->party_a_received_at !== null : $box->party_b_received_at !== null,
            'transaction_id' => $box->transaction_id,
            'expires_at' => $box->expires_at,
            'obligations' => $box->obligations->map(fn ($item) => [
                'id' => $item->id,
                'party_side' => $item->party_side,
                'type' => $item->type,
                'amount' => $item->amount,
                'status' => $item->status,
                'transaction_payment_id' => $item->transaction_payment_id,
            ])->values(),
            'handover_steps' => $box->handoverSteps->map(fn ($step) => [
                'id' => $step->id,
                'party_side' => $step->party_side,
                'step_type' => $step->step_type,
                'status' => $step->status,
                'sequence_no' => $step->sequence_no,
                'customer_note' => $step->party_side === $side ? $step->customer_note : null,
                'admin_note' => $step->admin_note,
                'submitted_at' => $step->submitted_at,
                'verified_at' => $step->verified_at,
                'expected_version' => $step->expected_version,
                'media_count' => $step->media->count(),
            ])->values(),
            'events' => $box->events->map(function ($event) {
                $actorLabel = $event->actor_side
                    ? ($event->actor_side === 'party_a' ? 'Bên A' : 'Bên B')
                    : ($event->actor_type === 'user' ? 'Nền tảng' : 'Hệ thống');
                $metadata = $this->sanitizeMetadata($event->metadata ?? []);

                return [
                    'id' => $event->id,
                    'event' => $event->event_type,
                    'event_type' => $event->event_type,
                    'description' => $this->eventLabel($event->event_type),
                    'actor' => ['id' => null, 'name' => $actorLabel, 'avatar_url' => null],
                    'actor_label' => $actorLabel,
                    'subject' => ['type' => 'escrow_box', 'id' => $event->escrow_box_id, 'label' => null],
                    'module' => 'escrow_box',
                    'metadata' => ['old' => null, 'new' => $metadata, 'subtype' => $event->event_type],
                    'occurred_at' => $event->occurred_at,
                ];
            })->values(),
            'created_at' => $box->created_at,
            'updated_at' => $box->updated_at,
        ];
    }

    public function invitePreview(EscrowBox $box): array
    {
        return [
            'code' => $box->code,
            'status' => $box->status,
            'deal_type' => $box->deal_type,
            'agreement_version' => $box->agreement_version,
            'agreement_terms' => $box->agreement_terms,
            'creator_label' => 'Bên A',
            'counterparty_label' => 'Bên B',
            'topup_payer_side' => $box->topup_payer_side,
            'topup_amount' => $box->topup_amount,
            'fee_payer_mode' => $box->fee_payer_mode,
            'estimated_fee' => $box->calculated_fee,
            'inspection_period_minutes' => $box->inspection_period_minutes,
            'expires_at' => $box->invite_expires_at,
        ];
    }

    public function assignedInvitePreview(EscrowBox $box, string $side): array
    {
        return [
            'code' => $box->code,
            'status' => $box->status,
            'deal_type' => $box->deal_type,
            'agreement_version' => $box->agreement_version,
            'agreement_terms' => $box->agreement_terms,
            'self_role' => $side,
            'self_label' => $side === 'party_a' ? 'Bên A' : 'Bên B',
            'counterparty_label' => $side === 'party_a' ? 'Bên B' : 'Bên A',
            'topup_payer_side' => $box->topup_payer_side,
            'topup_amount' => $box->topup_amount,
            'fee_payer_mode' => $box->fee_payer_mode,
            'estimated_fee' => $box->calculated_fee,
            'inspection_period_minutes' => $box->inspection_period_minutes,
            'expires_at' => $side === 'party_a' ? $box->party_a_invite_expires_at : $box->party_b_invite_expires_at,
        ];
    }

    public function admin(EscrowBox $box): array
    {
        return array_merge($box->toArray(), [
            'party_a' => $box->partyA?->only(['id', 'code', 'name', 'username', 'status']),
            'party_b' => $box->partyB?->only(['id', 'code', 'name', 'username', 'status']),
            'party_a_invite_accepted' => $box->party_a_invite_accepted_at !== null,
            'party_b_invite_accepted' => $box->party_b_invite_accepted_at !== null,
            'agreement_history' => $box->agreementVersions
                ->sortByDesc('version')
                ->map(fn ($version) => [
                    'id' => $version->id,
                    'version' => $version->version,
                    'changed_by_side' => $version->changed_by_side,
                    'changed_by_customer_id' => $version->changed_by_customer_id,
                    'changed_by_user_id' => $version->changed_by_user_id,
                    'change_note' => $version->change_note,
                    'terms' => $version->terms,
                    'created_at' => $version->created_at,
                ])->values(),
        ]);
    }

    private function eventLabel(string $eventType): string
    {
        return [
            'box_created' => 'Đã tạo Box',
            'admin_box_created' => 'Admin đã tạo Box',
            'counterparty_claimed' => 'Bên B đã tham gia',
            'counterparty_invited_by_phone' => 'Đã gửi lời mời cho Bên B',
            'counterparty_invite_cancelled' => 'Đã hủy lời mời Bên B',
            'counterparty_invite_accepted' => 'Bên B đã chấp nhận lời mời',
            'invite_rotated' => 'Đã vô hiệu hóa link cũ và tạo link mới',
            'terms_updated' => 'Đã cập nhật điều khoản',
            'party_confirmed' => 'Một bên đã xác nhận điều khoản',
            'terms_confirmed' => 'Hai bên đã xác nhận điều khoản',
            'changes_requested' => 'Admin yêu cầu bổ sung',
            'admin_rejected' => 'Admin từ chối Box',
            'admin_approved' => 'Admin phê duyệt Box',
            'handover_submitted' => 'Đã gửi bằng chứng bàn giao',
            'handover_verified' => 'Admin đã xác minh bàn giao',
            'receipt_confirmed' => 'Đã xác nhận nhận tài sản',
            'dispute_opened' => 'Đã mở tranh chấp',
            'box_settled' => 'Box đã hoàn tất quyết toán',
            'box_cancelled' => 'Người tạo đã hủy Box',
            'box_cancelled_by_admin' => 'Admin đã hủy Box',
        ][$eventType] ?? $eventType;
    }

    private function side(EscrowBox $box, int $customerId): ?string
    {
        if ((int) $box->party_a_customer_id === $customerId) {
            return 'party_a';
        }
        if ((int) $box->party_b_customer_id === $customerId) {
            return 'party_b';
        }

        return null;
    }

    private function sanitizeMetadata(array $metadata): array
    {
        return collect($metadata)->except(['customer_id', 'party_a_customer_id', 'party_b_customer_id', 'username', 'email', 'phone'])->all();
    }
}
