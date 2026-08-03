<?php

namespace App\Services\Marketplace\Operations;

use App\Models\AuditLog;
use App\Models\MarketplaceDispute;
use App\Models\Transaction;
use App\Models\WithdrawalRequest;
use Illuminate\Validation\ValidationException;

class OperationalTimelinePresenter
{
    public function present(string $subjectType, int $subjectId): array
    {
        return match ($subjectType) {
            'transaction' => $this->transaction($subjectId),
            'withdrawal' => $this->withdrawal($subjectId),
            'support' => $this->support($subjectId),
            default => throw ValidationException::withMessages(['subject_type' => 'Loại timeline không được hỗ trợ.']),
        };
    }

    private function transaction(int $id): array
    {
        $transaction = Transaction::query()->with(['events', 'payments', 'documents.acceptances', 'disputes'])->findOrFail($id);
        $items = collect($transaction->events)->map(fn ($event) => [
            'type' => 'transaction_event',
            'title' => $event->title ?: $event->event_type,
            'description' => $event->description,
            'actor_type' => $event->actor_type,
            'actor_id' => $event->actor_id,
            'metadata' => $event->metadata,
            'occurred_at' => $event->created_at,
        ]);

        return ['subject' => ['type' => 'transaction', 'id' => $transaction->id, 'code' => $transaction->code], 'items' => $items->sortByDesc('occurred_at')->values()];
    }

    private function withdrawal(int $id): array
    {
        $withdrawal = WithdrawalRequest::query()->with(['customer:id,code,name', 'payoutAccount'])->findOrFail($id);
        $items = AuditLog::query()
            ->where(fn ($query) => $query
                ->where(fn ($q) => $q->where('entity_type', 'withdrawal_request')->where('entity_id', (string) $id))
                ->orWhere(fn ($q) => $q->where('context_type', 'withdrawal')->where('context_id', (string) $id)))
            ->latest('id')->limit(100)->get()->map(fn ($log) => [
                'type' => 'audit',
                'title' => $log->title,
                'description' => $log->description,
                'actor_type' => $log->actor_type,
                'actor_id' => $log->actor_id,
                'metadata' => $log->metadata,
                'occurred_at' => $log->created_at,
            ]);

        return ['subject' => ['type' => 'withdrawal', 'id' => $withdrawal->id, 'code' => $withdrawal->code], 'items' => $items->values()];
    }

    private function support(int $id): array
    {
        $case = MarketplaceDispute::query()->with(['messages'])->findOrFail($id);
        $items = collect($case->messages)->map(fn ($message) => [
            'type' => 'support_message',
            'title' => $message->is_internal ? 'Ghi chú nội bộ' : 'Trao đổi hỗ trợ',
            'description' => $message->message,
            'actor_type' => $message->actor_type,
            'actor_id' => $message->actor_id,
            'metadata' => ['attachments' => $message->attachments],
            'occurred_at' => $message->created_at,
        ]);

        return ['subject' => ['type' => 'support', 'id' => $case->id, 'code' => $case->code], 'items' => $items->sortByDesc('occurred_at')->values()];
    }
}
