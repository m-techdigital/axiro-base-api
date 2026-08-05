<?php

namespace Tests\Feature;

use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Models\Transaction;
use App\Services\Documents\DocumentTemplateVersioningService;
use App\Services\Documents\MarketplaceDocumentService;
use App\Services\Documents\MarketplaceDocumentTemplateValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MarketplaceDocumentTemplateQualityTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_templates_are_substantive_and_render_without_unresolved_fields(): void
    {
        $this->seed();
        $this->assertGreaterThanOrEqual(13, DocumentTemplate::query()->count());
        DocumentTemplate::query()->each(function (DocumentTemplate $template) {
            $this->assertStringNotContainsString('{{listing_', $template->content_html, $template->code);
            $this->assertStringNotContainsString('{{listing_type}}', $template->content_html, $template->code);
            $plain = strip_tags($template->content_html);
            $this->assertGreaterThan(500, mb_strlen($plain), $template->code);
            $this->assertStringContainsString('quyền và nghĩa vụ', mb_strtolower($plain));
            $this->assertStringContainsString('tranh chấp', mb_strtolower($plain));
            $this->assertStringContainsString('xác nhận điện tử', mb_strtolower($plain));
        });
        $transaction = Transaction::query()->where('code', 'TRX-DEMO-COMPLETED-SALE')->firstOrFail();
        app(MarketplaceDocumentService::class)->ensureForTransaction($transaction);
        GeneratedDocument::query()->where('transaction_id', $transaction->id)->each(function (GeneratedDocument $document) {
            $this->assertDoesNotMatchRegularExpression('/\{\{[^}]+\}\}/', $document->rendered_html);
            $this->assertStringContainsString($document->transaction->code, $document->rendered_html);
            if (in_array($document->document_type, ['handover_minutes', 'security_checklist'], true)) {
                $this->assertStringContainsString('Bàn giao quyền truy cập tài khoản', $document->rendered_html);
                $this->assertStringContainsString('30 phút', $document->rendered_html);
            }
        });
    }

    public function test_used_template_update_creates_new_version_and_republishes_documents(): void
    {
        $this->seed();
        $transaction = Transaction::query()->where('code', 'TRX-DEMO-COMPLETED-SALE')->firstOrFail();
        app(MarketplaceDocumentService::class)->generate($transaction, 'sale_record');

        $template = DocumentTemplate::query()->where('code', 'sale_record')->where('version', 3)->firstOrFail();
        $oldContent = $template->content_html;
        $newContent = str_replace('Cảnh báo rủi ro:', 'Cảnh báo rủi ro bản phát hành mới:', $oldContent);

        $next = app(DocumentTemplateVersioningService::class)->update($template, [
            'code' => $template->code,
            'name' => $template->name,
            'type' => $template->type,
            'target_module' => $template->target_module,
            'status' => 'published',
            'version' => $template->version,
            'merge_fields' => $template->merge_fields,
            'content_html' => $newContent,
            'description' => $template->description,
        ]);

        $this->assertSame(4, $next->version);
        $this->assertSame($template->id, $next->supersedes_template_id);
        $this->assertSame('deprecated', $template->fresh()->status);
        $this->assertNotNull($template->fresh()->deprecated_at);
        $this->assertSame($oldContent, $template->fresh()->content_html);

        $historicalDocument = GeneratedDocument::query()
            ->where('transaction_id', $transaction->id)
            ->where('document_type', 'sale_record')
            ->latest('version')
            ->firstOrFail();

        $this->assertSame(1, $historicalDocument->version);
        $this->assertSame($template->id, $historicalDocument->document_template_id);
        $this->assertStringNotContainsString('Cảnh báo rủi ro bản phát hành mới', $historicalDocument->rendered_html);

        $regenerated = app(MarketplaceDocumentService::class)->generate($transaction, 'sale_record', null, true);
        $this->assertSame($next->id, $regenerated->document_template_id);
        $this->assertStringContainsString('Cảnh báo rủi ro bản phát hành mới', $regenerated->rendered_html);
    }

    public function test_used_template_cannot_branch_after_a_successor_exists(): void
    {
        $this->seed();
        $transaction = Transaction::query()->where('code', 'TRX-DEMO-COMPLETED-SALE')->firstOrFail();
        app(MarketplaceDocumentService::class)->generate($transaction, 'sale_record');
        $template = DocumentTemplate::query()->where('code', 'sale_record')->where('version', 3)->firstOrFail();
        $payload = [
            'code' => $template->code,
            'name' => $template->name,
            'type' => $template->type,
            'target_module' => $template->target_module,
            'status' => 'published',
            'version' => $template->version,
            'merge_fields' => $template->merge_fields,
            'content_html' => str_replace('Cảnh báo rủi ro:', 'Cảnh báo rủi ro phiên bản mới:', $template->content_html),
            'description' => $template->description,
        ];

        $next = app(DocumentTemplateVersioningService::class)->update($template, $payload);
        $this->assertSame(4, $next->version);

        $this->expectException(ValidationException::class);
        app(DocumentTemplateVersioningService::class)->update($template->fresh(), $payload);
    }

    public function test_template_validator_requires_core_legal_sections(): void
    {
        $this->expectException(ValidationException::class);

        app(MarketplaceDocumentTemplateValidator::class)->validateOrFail(
            'sale_record',
            '<h1>Mẫu ngắn</h1><p>{{transaction_code}} {{document_date}} {{buyer_name}} {{seller_name}} {{product_name}} {{total_payable}} {{transaction_value}} {{payment_schedule}}</p>'
        );
    }
}
