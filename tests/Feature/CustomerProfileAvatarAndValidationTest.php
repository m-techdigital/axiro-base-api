<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerProfileAvatarAndValidationTest extends TestCase
{
    use RefreshDatabase;

    private function headers(Customer $customer): array
    {
        return ['Authorization' => 'Bearer '.auth('customer_api')->login($customer), 'Accept' => 'application/json'];
    }

    public function test_customer_can_upload_and_persist_avatar(): void
    {
        Storage::fake('public');
        $customer = Customer::factory()->create();
        $response = $this->post('/api/v1/customer/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.webp', 300, 300),
        ], $this->headers($customer));
        $response->assertOk()->assertJsonPath('data.id', $customer->id);
        $this->assertNotNull($customer->fresh()->avatar_url);
    }

    public function test_profile_validation_is_vietnamese_and_field_addressable(): void
    {
        $customer = Customer::factory()->create();
        $this->putJson('/api/v1/customer/profile', ['name' => '', 'phone' => str_repeat('1', 31)], $this->headers($customer))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'phone'])
            ->assertJsonPath('status.message', 'Dữ liệu chưa hợp lệ. Vui lòng kiểm tra các trường được đánh dấu.');
    }
}
