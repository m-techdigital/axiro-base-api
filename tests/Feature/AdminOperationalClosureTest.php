<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MarketplaceNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOperationalClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_mark_notification_handled_and_today_dashboard_exposes_work_queues(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::factory()->create();
        $notification = MarketplaceNotification::query()->create([
            'customer_id' => $customer->id, 'type' => 'support_case', 'title' => 'Cần xử lý',
            'message' => 'Kiểm tra hồ sơ khách hàng.',
        ]);
        $headers = ['Authorization' => 'Bearer '.auth('api')->login($admin)];

        $this->postJson('/api/v1/notifications/'.$notification->id.'/handle', ['note' => 'Đã kiểm tra và xử lý.'], $headers)
            ->assertOk()->assertJsonPath('data.handling_note', 'Đã kiểm tra và xử lý.');

        $this->getJson('/api/v1/operations-dashboard/today', $headers)
            ->assertOk()->assertJsonStructure(['data' => ['counters', 'queues' => ['payouts', 'transactions', 'notifications']]]);
    }

    public function test_operational_timeline_rejects_unknown_subject_type(): void
    {
        $admin = User::factory()->create();
        $headers = ['Authorization' => 'Bearer '.auth('api')->login($admin)];
        $this->getJson('/api/v1/operations-dashboard/timeline/unknown/1', $headers)->assertStatus(422);
    }
}
