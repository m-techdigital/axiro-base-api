<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeepBaseQueryAdoptionTest extends TestCase
{
    #[Test]
    public function list_query_contract_covers_documents_audit_and_notifications(): void
    {
        $request = file_get_contents(app_path('Http/Requests/Common/ListQueryRequest.php'));

        foreach (['document_type', 'audit_type', 'risk_level', 'unread'] as $field) {
            $this->assertStringContainsString("'{$field}'", $request);
        }

        foreach ([
            'AuditLogController.php',
            'GeneratedDocumentController.php',
            'CustomerNotificationController.php',
        ] as $controller) {
            $source = file_get_contents(app_path("Http/Controllers/{$controller}"));
            $this->assertStringContainsString('AppliesListQuery', $source);
            $this->assertStringContainsString('ListQueryRequest', $source);
        }
    }
}
