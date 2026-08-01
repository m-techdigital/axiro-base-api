<?php

namespace Tests\Feature;

use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Models\Transaction;
use App\Services\Documents\MarketplaceDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        });
    }
}
