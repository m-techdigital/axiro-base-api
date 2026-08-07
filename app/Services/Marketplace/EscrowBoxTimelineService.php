<?php

namespace App\Services\Marketplace;

use App\Models\Customer;
use App\Models\EscrowBox;
use App\Models\EscrowBoxAgreementVersion;
use App\Models\EscrowBoxEvent;
use App\Models\User;
use App\Support\AuditPayloadSanitizer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class EscrowBoxTimelineService
{
    private const EVENT_LABELS = [
        'box_created' => 'Đã tạo Box',
        'admin_box_created' => 'Admin đã tạo Box',
        'counterparty_claimed' => 'Bên B đã tham gia',
        'counterparty_invited_by_phone' => 'Đã gửi lời mời cho Bên B',
        'counterparty_invite_cancelled' => 'Đã hủy lời mời Bên B',
        'counterparty_invite_accepted' => 'Bên B đã chấp nhận lời mời',
        'assigned_party_accepted' => 'Một bên đã chấp nhận lời mời',
        'assigned_parties_accepted' => 'Hai bên đã chấp nhận lời mời',
        'assigned_invites_rotated' => 'Admin đã tạo lại link xác nhận',
        'invite_rotated' => 'Đã vô hiệu hóa link cũ và tạo link mới',
        'terms_updated' => 'Đã cập nhật điều khoản',
        'party_confirmed' => 'Một bên đã xác nhận điều khoản',
        'terms_confirmed' => 'Hai bên đã xác nhận điều khoản',
        'changes_requested' => 'Admin yêu cầu bổ sung',
        'admin_rejected' => 'Admin từ chối Box',
        'admin_approved' => 'Admin phê duyệt Box',
        'handover_submitted' => 'Đã gửi bằng chứng bàn giao',
        'handover_verified' => 'Admin đã xác minh bàn giao',
        'handover_changes_requested' => 'Admin yêu cầu bổ sung bằng chứng',
        'receipt_confirmed' => 'Đã xác nhận nhận tài sản',
        'dispute_opened' => 'Đã mở tranh chấp',
        'box_settled' => 'Box đã hoàn tất quyết toán',
        'box_cancelled' => 'Người tạo đã hủy Box',
        'box_cancelled_by_admin' => 'Admin đã hủy Box',
    ];

    private const ACTIVITY_EVENT_TYPES = [
        'created' => ['box_created', 'admin_box_created'],
        'updated' => ['terms_updated'],
        'approved' => ['admin_approved'],
        'deleted' => ['box_cancelled', 'box_cancelled_by_admin', 'admin_rejected'],
        'status_changed' => ['box_settled', 'receipt_confirmed', 'terms_confirmed', 'assigned_parties_accepted'],
    ];

    public function list(
        EscrowBox $box,
        array $filters = [],
        string $audience = 'admin',
        ?int $viewerCustomerId = null,
    ): LengthAwarePaginator {
        if ($audience === 'customer') {
            abort_unless($viewerCustomerId !== null && $this->side($box, $viewerCustomerId) !== null, 404);
        }

        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? $filters['limit'] ?? 10)));
        $query = EscrowBoxEvent::query()
            ->where('escrow_box_id', $box->id)
            ->when(
                ! empty($filters['activity_subtype']),
                fn ($builder) => $builder->where('event_type', $filters['activity_subtype']),
            )
            ->when(
                ! empty($filters['activity_type']),
                fn ($builder) => $this->applyActivityTypeFilter($builder, (string) $filters['activity_type']),
            )
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        $paginator = $query->paginate($perPage, ['*'], 'page', max(1, (int) ($filters['page'] ?? 1)));
        $events = collect($paginator->items());

        $customerNames = $audience === 'admin'
            ? Customer::query()
                ->whereIn('id', $events->where('actor_type', 'customer')->pluck('actor_id')->filter()->unique())
                ->pluck('name', 'id')
            : collect();
        $userNames = $audience === 'admin'
            ? User::query()
                ->whereIn('id', $events->where('actor_type', 'user')->pluck('actor_id')->filter()->unique())
                ->pluck('name', 'id')
            : collect();

        $versionNumbers = $events
            ->map(fn (EscrowBoxEvent $event) => (int) data_get($event->metadata, 'agreement_version', 0))
            ->filter()
            ->flatMap(fn (int $version) => [$version, $version - 1])
            ->filter(fn (int $version) => $version > 0)
            ->unique()
            ->values();
        $versionByNumber = EscrowBoxAgreementVersion::query()
            ->where('escrow_box_id', $box->id)
            ->whereIn('version', $versionNumbers)
            ->get()
            ->keyBy('version');

        $paginator->setCollection(
            $events->map(
                fn (EscrowBoxEvent $event) => $this->present(
                    $box,
                    $event,
                    $audience,
                    $customerNames,
                    $userNames,
                    $versionByNumber,
                ),
            ),
        );

        return $paginator;
    }

    private function applyActivityTypeFilter($query, string $activityType): void
    {
        $eventTypes = self::ACTIVITY_EVENT_TYPES[$activityType] ?? null;

        if ($eventTypes !== null) {
            $query->whereIn('event_type', $eventTypes);

            return;
        }

        if ($activityType === 'interaction') {
            $knownTypes = collect(self::ACTIVITY_EVENT_TYPES)->flatten()->all();
            $query->whereNotIn('event_type', $knownTypes);

            return;
        }

        $query->whereRaw('1 = 0');
    }

    private function present(
        EscrowBox $box,
        EscrowBoxEvent $event,
        string $audience,
        Collection $customerNames,
        Collection $userNames,
        Collection $versionByNumber,
    ): array {
        $version = (int) data_get($event->metadata, 'agreement_version', 0);
        $currentVersion = $version > 0 ? $versionByNumber->get($version) : null;
        $previousVersion = $version > 1 ? $versionByNumber->get($version - 1) : null;

        $old = $event->event_type === 'terms_updated'
            ? AuditPayloadSanitizer::sanitize($previousVersion?->terms ?? [])
            : null;
        $new = $event->event_type === 'terms_updated'
            ? AuditPayloadSanitizer::sanitize($currentVersion?->terms ?? [])
            : AuditPayloadSanitizer::sanitize($event->metadata ?? []);

        $actor = $this->actor(
            $event->actor_type,
            $event->actor_id,
            $event->actor_side,
            $audience,
            $customerNames,
            $userNames,
        );
        $activityType = $this->activityType($event->event_type);
        $description = self::EVENT_LABELS[$event->event_type] ?? $event->event_type;

        return [
            'id' => 'event-'.$event->id,
            'timeline_key' => 'escrow-box-event:'.$event->id,
            'audit_type' => 'business_activity',
            'event' => $activityType,
            'description' => $description,
            'actor' => $actor,
            'subject' => ['type' => 'escrow_box', 'id' => $box->id, 'label' => $box->code],
            'module' => 'escrow_box',
            'metadata' => ['old' => $old, 'new' => $new, 'subtype' => $event->event_type],
            'details' => ['agreement_version' => $version ?: null],
            'occurred_at' => $event->occurred_at,
            'entity_type' => 'escrow_box',
            'entity_id' => $box->id,
            'entity_label' => $box->code,
            'activity_type' => $activityType,
            'activity_subtype' => $event->event_type,
            'title' => $description,
            'notes' => data_get($event->metadata, 'note') ?? data_get($event->metadata, 'reason'),
            'old' => $old,
            'new' => $new,
            'changed_by' => $actor,
            'created_at' => $event->occurred_at,
        ];
    }

    private function actor(
        string $type,
        ?int $id,
        ?string $side,
        string $audience,
        Collection $customerNames,
        Collection $userNames,
    ): ?array {
        if ($type === 'system') {
            return ['id' => null, 'name' => 'Hệ thống', 'avatar_url' => null];
        }

        if ($audience === 'customer') {
            return [
                'id' => null,
                'name' => $side === 'party_a' ? 'Bên A' : ($side === 'party_b' ? 'Bên B' : 'Nền tảng'),
                'avatar_url' => null,
            ];
        }

        if ($type === 'user') {
            return ['id' => $id, 'name' => $userNames->get($id) ?: 'Admin', 'avatar_url' => null];
        }

        return [
            'id' => $id,
            'name' => $customerNames->get($id) ?: ($side === 'party_a' ? 'Bên A' : 'Bên B'),
            'avatar_url' => null,
        ];
    }

    private function activityType(string $eventType): string
    {
        foreach (self::ACTIVITY_EVENT_TYPES as $activityType => $eventTypes) {
            if (in_array($eventType, $eventTypes, true)) {
                return $activityType;
            }
        }

        return 'interaction';
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
}
