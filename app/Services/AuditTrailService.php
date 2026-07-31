<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Support\AuditPayloadSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AuditTrailService
{
    public function log(array $payload): ?AuditLog
    {
        try {
            if (!Schema::hasTable('audit_logs')) return null;
            $request = request();
            [$actorType, $actorId] = $this->actor();
            return AuditLog::query()->create([
                'audit_type' => $payload['audit_type'] ?? 'business_trail',
                'event_type' => $payload['event_type'] ?? 'changed',
                'risk_level' => $payload['risk_level'] ?? $this->riskLevel($payload),
                'actor_type' => $payload['actor_type'] ?? $actorType,
                'actor_id' => $payload['actor_id'] ?? $actorId,
                'entity_type' => $payload['entity_type'] ?? null,
                'entity_id' => isset($payload['entity_id']) ? (string) $payload['entity_id'] : null,
                'context_type' => $payload['context_type'] ?? null,
                'context_id' => isset($payload['context_id']) ? (string) $payload['context_id'] : null,
                'request_id' => $payload['request_id'] ?? $request?->attributes->get('request_id'),
                'correlation_id' => $payload['correlation_id'] ?? $request?->attributes->get('correlation_id'),
                'route_name' => $payload['route_name'] ?? $request?->route()?->getName(),
                'path' => $payload['path'] ?? ($request ? '/'.ltrim($request->path(), '/') : null),
                'method' => $payload['method'] ?? $request?->method(),
                'status_code' => $payload['status_code'] ?? null,
                'title' => $payload['title'] ?? 'Thay đổi dữ liệu',
                'description' => $payload['description'] ?? null,
                'old_values' => AuditPayloadSanitizer::sanitize($payload['old_values'] ?? null),
                'new_values' => AuditPayloadSanitizer::sanitize($payload['new_values'] ?? null),
                'changed_fields' => $payload['changed_fields'] ?? null,
                'validation_errors' => AuditPayloadSanitizer::sanitize($payload['validation_errors'] ?? null),
                'metadata' => AuditPayloadSanitizer::sanitize($payload['metadata'] ?? null),
                'ip_address' => $payload['ip_address'] ?? $request?->ip(),
                'user_agent' => $payload['user_agent'] ?? $request?->userAgent(),
            ]);
        } catch (\Throwable $exception) {
            report($exception);
            return null;
        }
    }

    public function modelChanged(Model $model, string $event, array $old = [], array $new = []): ?AuditLog
    {
        [$contextType, $contextId] = $this->contextFor($model);
        $entityType = Str::snake(class_basename($model));
        $changedFields = array_values(array_unique(array_merge(array_keys($old), array_keys($new))));
        return $this->log([
            'audit_type' => 'business_trail',
            'event_type' => $event,
            'entity_type' => $entityType,
            'entity_id' => $model->getKey(),
            'context_type' => $contextType,
            'context_id' => $contextId,
            'title' => $this->eventTitle($entityType, $event),
            'description' => $this->eventDescription($model, $event, $changedFields),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'changed_fields' => $changedFields ?: null,
            'metadata' => ['model' => get_class($model)],
        ]);
    }

    public function validationFailure(Request $request, array $errors): ?AuditLog
    {
        return $this->log([
            'audit_type' => 'validation',
            'event_type' => 'validation_failed',
            'risk_level' => 'warning',
            'status_code' => 422,
            'title' => 'Yêu cầu bị từ chối do dữ liệu chưa hợp lệ',
            'description' => collect($errors)->flatten()->take(4)->implode(' '),
            'validation_errors' => $errors,
            'metadata' => [
                'payload' => AuditPayloadSanitizer::sanitize($request->all()),
                'route_parameters' => AuditPayloadSanitizer::sanitize($request->route()?->parameters() ?? []),
            ],
        ]);
    }

    public function forTransaction(int $transactionId, int $limit = 100)
    {
        return AuditLog::query()
            ->where(function ($query) use ($transactionId) {
                $query->where(fn ($q) => $q->where('entity_type', 'transaction')->where('entity_id', (string) $transactionId))
                    ->orWhere(fn ($q) => $q->where('context_type', 'transaction')->where('context_id', (string) $transactionId));
            })
            ->latest('id')->limit($limit)->get();
    }

    public function actor(): array
    {
        if (auth('api')->check()) return ['admin', auth('api')->id()];
        if (auth('customer_api')->check()) return ['customer', auth('customer_api')->id()];
        return [app()->runningInConsole() ? 'system' : 'guest', null];
    }

    private function contextFor(Model $model): array
    {
        if ($model->getAttribute('transaction_id')) return ['transaction', $model->getAttribute('transaction_id')];
        if ($model instanceof \App\Models\Transaction) return ['transaction', $model->getKey()];
        if ($model->getAttribute('listing_id')) return ['product_listing', $model->getAttribute('listing_id')];
        if ($model->getAttribute('product_id')) return ['product', $model->getAttribute('product_id')];
        return [null, null];
    }

    private function riskLevel(array $payload): string
    {
        $event = $payload['event_type'] ?? '';
        if (str_contains($event, 'delete') || str_contains($event, 'reject') || str_contains($event, 'cancel') || str_contains($event, 'dispute')) return 'high';
        if (($payload['status_code'] ?? 200) >= 400) return 'warning';
        return 'normal';
    }

    private function eventTitle(string $entityType, string $event): string
    {
        $labels = ['created' => 'được tạo', 'updated' => 'được cập nhật', 'deleted' => 'bị xóa', 'restored' => 'được khôi phục'];
        return Str::headline($entityType).' '.($labels[$event] ?? $event);
    }

    private function eventDescription(Model $model, string $event, array $fields): string
    {
        $code = $model->getAttribute('code') ?: $model->getKey();
        $suffix = $fields ? ' Các trường thay đổi: '.implode(', ', $fields).'.' : '';
        return sprintf('%s #%s %s.%s', class_basename($model), $code, $event, $suffix);
    }
}
