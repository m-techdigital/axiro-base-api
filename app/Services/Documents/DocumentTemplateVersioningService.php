<?php

namespace App\Services\Documents;

use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use Illuminate\Support\Facades\DB;

class DocumentTemplateVersioningService
{
    public function __construct(private readonly MarketplaceDocumentService $documents) {}

    public function update(DocumentTemplate $template, array $data, ?int $adminId = null): DocumentTemplate
    {
        app(MarketplaceDocumentTemplateValidator::class)->validateOrFail($data['type'], $data['content_html']);

        return DB::transaction(function () use ($template, $data, $adminId) {
            $template->refresh();
            $isIssued = $template->generatedDocuments()->exists();
            $data['updated_by'] = $adminId;

            if (! $isIssued) {
                $template->update($data);

                return $template->fresh(['supersedes'])->loadCount('generatedDocuments');
            }

            $latestVersion = (int) DocumentTemplate::query()
                ->where('code', $template->code)
                ->withTrashed()
                ->max('version');

            $template->update(['status' => 'archived', 'updated_by' => $adminId]);

            $next = DocumentTemplate::query()->create([
                ...$data,
                'code' => $template->code,
                'version' => max($latestVersion, $template->version) + 1,
                'supersedes_template_id' => $template->id,
                'created_by' => $template->created_by,
                'updated_by' => $adminId,
            ]);

            if ($next->status === 'approved') {
                $this->republishIssuedDocuments($template, $adminId);
            }

            return $next->fresh(['supersedes'])->loadCount('generatedDocuments');
        });
    }

    private function republishIssuedDocuments(DocumentTemplate $oldTemplate, ?int $adminId): void
    {
        GeneratedDocument::query()
            ->where('document_template_id', $oldTemplate->id)
            ->select(['transaction_id', 'document_type'])
            ->distinct()
            ->with('transaction')
            ->get()
            ->each(function (GeneratedDocument $document) use ($adminId) {
                if ($document->transaction) {
                    $this->documents->generate($document->transaction, $document->document_type, $adminId, true);
                }
            });
    }
}
