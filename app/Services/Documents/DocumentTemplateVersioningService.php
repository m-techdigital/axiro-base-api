<?php

namespace App\Services\Documents;

use App\Models\DocumentTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentTemplateVersioningService
{
    public function update(DocumentTemplate $template, array $data, ?int $adminId = null): DocumentTemplate
    {
        app(MarketplaceDocumentTemplateValidator::class)->validateOrFail($data['type'], $data['content_html']);

        return DB::transaction(function () use ($template, $data, $adminId) {
            $template = DocumentTemplate::query()->lockForUpdate()->findOrFail($template->id);
            $isIssued = $template->generatedDocuments()->exists();
            $data['updated_by'] = $adminId;

            if ($isIssued && $template->successors()->exists()) {
                $latest = DocumentTemplate::query()
                    ->where('code', $template->code)
                    ->latest('version')
                    ->first();
                throw ValidationException::withMessages([
                    'version' => 'Mẫu này đã có phiên bản kế tiếp. Hãy chỉnh sửa phiên bản mới nhất v'.($latest?->version ?? $template->version).'.',
                ]);
            }

            if (! $isIssued) {
                $data['published_at'] = ($data['status'] ?? null) === 'published' ? ($template->published_at ?? now()) : null;
                $data['deprecated_at'] = ($data['status'] ?? null) === 'deprecated' ? ($template->deprecated_at ?? now()) : null;
                $template->update($data);

                return $template->fresh(['supersedes'])->loadCount('generatedDocuments');
            }

            $latestVersion = (int) DocumentTemplate::query()
                ->where('code', $template->code)
                ->withTrashed()
                ->max('version');

            $template->update(['status' => 'deprecated', 'deprecated_at' => now(), 'updated_by' => $adminId]);

            $next = DocumentTemplate::query()->create([
                ...$data,
                'code' => $template->code,
                'version' => max($latestVersion, $template->version) + 1,
                'supersedes_template_id' => $template->id,
                'created_by' => $template->created_by,
                'published_at' => ($data['status'] ?? null) === 'published' ? now() : null,
                'deprecated_at' => null,
                'updated_by' => $adminId,
            ]);

            return $next->fresh(['supersedes'])->loadCount('generatedDocuments');
        });
    }
}
