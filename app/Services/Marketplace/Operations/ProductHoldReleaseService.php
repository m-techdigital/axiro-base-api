<?php

namespace App\Services\Marketplace\Operations;

use App\Exceptions\Marketplace\ProductHoldReleaseConflict;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductHold;
use App\Services\ProductAvailabilityService;

class ProductHoldReleaseService
{
    public function __construct(private readonly ProductAvailabilityService $availability) {}

    public function release(ProductHold $hold, array $data): Product
    {
        $hold->loadMissing('product');
        $product = $hold->product;

        if ($hold->status !== 'active') {
            throw new ProductHoldReleaseConflict('Lượt giữ chỗ này không còn hiệu lực.');
        }

        if (! $product || $product->availability_status !== 'held' || (int) $product->held_by_transaction_id !== (int) $hold->source_id) {
            throw new ProductHoldReleaseConflict('Sản phẩm không còn được giữ bởi lượt giữ chỗ này.');
        }

        $updated = $this->availability->transition(
            $product,
            'available',
            $hold->source,
            $data['note'],
            $data['expected_version'] ?? null,
            true,
        );

        AuditLog::query()->create([
            'audit_type' => 'business_trail',
            'event_type' => 'product_hold_manual_release',
            'risk_level' => 'medium',
            'actor_type' => 'user',
            'actor_id' => user_id(),
            'entity_type' => 'product_hold',
            'entity_id' => (string) $hold->id,
            'context_type' => 'product',
            'context_id' => (string) $product->id,
            'title' => 'Nhả giữ chỗ thủ công',
            'description' => $data['note'],
            'metadata' => ['availability_version' => $updated->availability_version],
        ]);

        return $updated->load('activeHold');
    }
}
