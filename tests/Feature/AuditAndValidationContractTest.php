<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditAndValidationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_response_is_clear_and_is_audited(): void
    {
        $customer = Customer::factory()->create(['password' => 'password']);
        $token = auth('customer_api')->login($customer);
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/customer/products', ['password' => 'do-not-log']);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['errors', 'meta' => ['request_id', 'correlation_id']]);

        $this->assertDatabaseHas('audit_logs', ['audit_type' => 'validation', 'event_type' => 'validation_failed', 'status_code' => 422]);
        $log = AuditLog::where('audit_type', 'validation')->firstOrFail();
        $this->assertSame('[Đã che]', data_get($log->metadata, 'payload.password'));
    }

    public function test_model_changes_and_http_mutations_are_audited(): void
    {
        $user = User::factory()->create(['username' => 'admin', 'password' => 'password']);
        $token = auth('api')->login($user);
        $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/v1/products', [
            'code' => 'AUD-001', 'name' => 'Sản phẩm kiểm tra', 'product_type' => 'game_account',
            'status' => 'active', 'price' => 100000,
        ])->assertCreated();

        $this->assertDatabaseHas('audit_logs', ['audit_type' => 'business_trail', 'event_type' => 'created', 'entity_type' => 'product']);
        $this->assertDatabaseHas('audit_logs', ['audit_type' => 'system_operation', 'event_type' => 'http_mutation', 'status_code' => 201]);
    }
}
