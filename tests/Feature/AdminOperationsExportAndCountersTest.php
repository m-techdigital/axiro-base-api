<?php

namespace Tests\Feature;

use App\Jobs\BuildRentalSettlementExport;
use App\Models\Customer;
use App\Models\MarketplaceNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminOperationsExportAndCountersTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function overview_exposes_unread_notification_counter(): void
    {
        $customer = Customer::factory()->create();
        MarketplaceNotification::query()->create([
            'customer_id' => $customer->id,
            'type' => 'system',
            'title' => 'Cần xử lý',
            'message' => 'Thông báo chưa đọc',
        ]);

        $this->getJson('/api/v1/operations-dashboard/overview', $this->adminHeaders())
            ->assertOk()
            ->assertJsonPath('data.menu_counters.unread_notifications', 1);
    }

    #[Test]
    public function admin_can_queue_and_poll_rental_settlement_export(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/operations-dashboard/rental-settlements/exports', [
            'status' => 'completed',
        ], $this->adminHeaders())->assertStatus(202);

        $id = $response->json('data.id');
        $this->assertDatabaseHas('marketplace_export_requests', [
            'id' => $id, 'type' => 'rental_settlement', 'status' => 'pending',
        ]);
        Queue::assertPushed(BuildRentalSettlementExport::class);

        $this->getJson('/api/v1/operations-dashboard/rental-settlements/exports/'.$id, $this->adminHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');
    }

    private function adminHeaders(): array
    {
        return ['Authorization' => 'Bearer '.auth('api')->login(User::factory()->create())];
    }
}
