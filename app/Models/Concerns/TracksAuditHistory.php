<?php

namespace App\Models\Concerns;

use App\Services\AuditTrailService;

trait TracksAuditHistory
{
    public static function bootTracksAuditHistory(): void
    {
        static::created(fn ($model) => app(AuditTrailService::class)->modelChanged($model, 'created', [], $model->getAttributes()));
        static::updated(function ($model) {
            $changes = $model->getChanges();
            unset($changes['updated_at']);
            if ($changes === []) return;
            $old = [];
            foreach (array_keys($changes) as $field) $old[$field] = $model->getOriginal($field);
            app(AuditTrailService::class)->modelChanged($model, 'updated', $old, $changes);
        });
        static::deleted(fn ($model) => app(AuditTrailService::class)->modelChanged($model, 'deleted', $model->getAttributes(), []));
        if (method_exists(static::class, 'restored')) {
            static::restored(fn ($model) => app(AuditTrailService::class)->modelChanged($model, 'restored', [], $model->getAttributes()));
        }
    }
}
