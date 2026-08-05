<?php

namespace App\Services\Marketplace;

use App\Models\EscrowBox;

class EscrowBoxPresenter
{
    public function customer(EscrowBox $box, int $customerId): array
    {
        $side = $this->side($box, $customerId);
        abort_unless($side !== null, 403);
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
            'events' => $box->events->map(fn ($event) => [
                'event_type' => $event->event_type,
                'actor_label' => $event->actor_side ? ($event->actor_side === 'party_a' ? 'Bên A' : 'Bên B') : ($event->actor_type === 'user' ? 'Nền tảng' : null),
                'metadata' => $this->sanitizeMetadata($event->metadata ?? []),
                'occurred_at' => $event->occurred_at,
            ])->values(),
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

    public function admin(EscrowBox $box): array
    {
        return array_merge($box->toArray(), [
            'party_a' => $box->partyA?->only(['id', 'code', 'name', 'username', 'status']),
            'party_b' => $box->partyB?->only(['id', 'code', 'name', 'username', 'status']),
        ]);
    }

    private function side(EscrowBox $box, int $customerId): ?string
    {
        if ((int) $box->party_a_customer_id === $customerId) return 'party_a';
        if ((int) $box->party_b_customer_id === $customerId) return 'party_b';
        return null;
    }

    private function sanitizeMetadata(array $metadata): array
    {
        return collect($metadata)->except(['customer_id', 'party_a_customer_id', 'party_b_customer_id', 'username', 'email', 'phone'])->all();
    }
}
