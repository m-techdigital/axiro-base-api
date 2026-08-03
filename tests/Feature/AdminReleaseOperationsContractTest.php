<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use App\Services\Documents\MarketplaceDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReleaseOperationsContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_template_list_exposes_version_status_and_used_count(): void
    {
        $this->seed();
        $transaction = Transaction::query()->where('code', 'TRX-DEMO-COMPLETED-SALE')->firstOrFail();
        app(MarketplaceDocumentService::class)->generate($transaction, 'sale_record');

        $response = $this->withHeaders($this->adminHeaders())->getJson('/api/v1/document-templates?type=sale_record');

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('code', 'sale_record');
        $this->assertSame('published', $row['status']);
        $this->assertGreaterThanOrEqual(1, $row['generated_documents_count']);
        $this->assertSame(3, $row['version']);
    }

    public function test_action_center_exposes_payment_payout_deposit_hold_dispute_and_rental_deposit_queues(): void
    {
        $this->seed();

        $response = $this->withHeaders($this->adminHeaders())->getJson('/api/v1/action-center');

        $response->assertOk();
        foreach ([
            'submitted_payments',
            'pending_deposits',
            'open_disputes',
            'rental_deposit_review',
            'pending_payouts',
            'active_holds',
            'expired_holds',
        ] as $key) {
            $this->assertArrayHasKey($key, $response->json('data.counts'));
        }
        foreach (['products', 'payments', 'deposits', 'disputes', 'transactions', 'rental_deposits', 'payouts', 'holds'] as $key) {
            $this->assertArrayHasKey($key, $response->json('data'));
        }
    }

    private function adminHeaders(): array
    {
        $admin = User::query()->first() ?? User::factory()->create();

        return ['Authorization' => 'Bearer '.auth('api')->login($admin)];
    }
}
