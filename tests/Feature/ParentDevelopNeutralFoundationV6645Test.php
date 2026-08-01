<?php

namespace Tests\Feature;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Common\DateRangeRequest;
use App\Http\Requests\Common\ExportFiltersRequest;
use App\Http\Requests\Common\OptionalNotesRequest;
use App\Http\Requests\Common\RequiredNotesRequest;
use App\Support\AuditPayloadSanitizer;
use App\Support\DatabaseExpressions;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ParentDevelopNeutralFoundationV6645Test extends TestCase
{
    public function test_audit_payload_sanitizer_masks_and_limits_parent_contract(): void
    {
        $payload = AuditPayloadSanitizer::sanitize([
            'password' => 'secret',
            'authorization' => 'Bearer token',
            'name' => str_repeat('a', 3100),
            'file' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
        ]);

        $this->assertSame('[Đã che]', $payload['password']);
        $this->assertSame('[Đã che]', $payload['authorization']);
        $this->assertLessThanOrEqual(3020, mb_strlen($payload['name']));
        $this->assertSame('proof.pdf', $payload['file']['file_name']);
    }

    public function test_database_expression_is_driver_aware(): void
    {
        $expression = DatabaseExpressions::greatest('paid_amount', 'refunded_amount');
        $this->assertStringContainsString('(', $expression);
        $this->assertStringContainsString('paid_amount', $expression);
        $this->assertStringContainsString('refunded_amount', $expression);
    }

    public function test_common_requests_preserve_mini_validation_boundary(): void
    {
        foreach ([DateRangeRequest::class, OptionalNotesRequest::class, RequiredNotesRequest::class, ExportFiltersRequest::class] as $request) {
            $this->assertTrue(is_subclass_of($request, ApiFormRequest::class));
        }

        $date = new DateRangeRequest;
        $this->assertSame(['required', 'date'], $date->rules()['start']);
        $this->assertContains('after_or_equal:start', $date->rules()['end']);
    }

    public function test_parent_heavy_domains_are_not_imported_into_neutral_foundation(): void
    {
        $manifest = file_get_contents(base_path('docs/canonical/parent-develop-neutral-sync-v66.45.json'));
        $this->assertStringNotContainsString('App\\Models\\Company', file_get_contents(app_path('Support/DatabaseExpressions.php')));
        $this->assertStringContainsString('excluded', $manifest);
    }
}
