<?php

namespace App\Services;

use App\Enums\ProductAvailabilityStatus;
use App\Models\Product;
use App\Models\ProductAvailabilityHistory;
use App\Models\ProductHold;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductAvailabilityService
{
    private const TRANSITIONS = [
        'available' => ['held', 'suspended'],
        'held' => ['available', 'transacting', 'sold', 'rented', 'suspended'],
        'transacting' => ['available', 'sold', 'rented', 'suspended'],
        'rented' => ['available', 'suspended'],
        'sold' => ['suspended'],
        'suspended' => ['available'],
    ];

    public function hold(Product $product, int $customerId, Model $source, int $minutes = 30, ?string $note = null, ?int $expectedVersion = null): Product
    {
        return DB::transaction(function () use ($product, $customerId, $source, $minutes, $note, $expectedVersion) {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            $this->assertVersion($locked, $expectedVersion);
            $this->assertTransition($locked->availability_status, ProductAvailabilityStatus::HELD->value);

            $active = ProductHold::query()->where('product_id', $locked->id)->where('status', 'active')->lockForUpdate()->first();
            if ($active) {
                throw ValidationException::withMessages(['product' => 'Sản phẩm đang có một lượt giữ chỗ hiệu lực.']);
            }

            $until = now()->addMinutes(max(1, $minutes));
            $from = $locked->availability_status;
            $locked->forceFill([
                'availability_status' => ProductAvailabilityStatus::HELD->value,
                'held_by_transaction_id' => $source->getKey(),
                'hold_expires_at' => $until,
                'availability_version' => ((int) $locked->availability_version) + 1,
                'unavailable_reason' => $note,
            ])->save();

            ProductHold::query()->create([
                'product_id' => $locked->id,
                'customer_id' => $customerId,
                'hold_until' => $until,
                'source_type' => $source->getMorphClass(),
                'source_id' => $source->getKey(),
                'status' => 'active',
                'note' => $note,
            ]);
            $this->history($locked, $from, ProductAvailabilityStatus::HELD->value, $customerId, $source, $until, $note);

            return $locked->fresh();
        });
    }

    public function transition(Product $product, ProductAvailabilityStatus|string $to, ?Model $source = null, ?string $note = null, ?int $expectedVersion = null, bool $adminOverride = false): Product
    {
        $to = $to instanceof ProductAvailabilityStatus ? $to->value : $to;

        return DB::transaction(function () use ($product, $to, $source, $note, $expectedVersion, $adminOverride) {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            $this->assertVersion($locked, $expectedVersion);
            $from = $locked->availability_status;
            if ($from === $to) {
                return $locked;
            }
            $this->assertTransition($from, $to);
            $this->assertSourceOwnership($locked, $source, $adminOverride);

            $locked->forceFill([
                'availability_status' => $to,
                'held_by_transaction_id' => in_array($to, ['held', 'transacting'], true) ? $source?->getKey() : null,
                'hold_expires_at' => $to === 'held' ? $locked->hold_expires_at : null,
                'availability_version' => ((int) $locked->availability_version) + 1,
                'unavailable_reason' => $note,
            ])->save();

            if (! in_array($to, ['held', 'transacting'], true)) {
                $this->closeActiveHold($locked, $note ?? 'released');
            }

            $this->history($locked, $from, $to, null, $source, $locked->hold_expires_at, $note);

            return $locked->fresh();
        });
    }

    public function expireHolds(int $limit = 200): int
    {
        $expired = 0;
        ProductHold::query()->where('status', 'active')->where('hold_until', '<=', now())->orderBy('id')->limit($limit)->get()->each(function (ProductHold $hold) use (&$expired) {
            DB::transaction(function () use ($hold, &$expired) {
                $lockedHold = ProductHold::query()->lockForUpdate()->find($hold->id);
                if (! $lockedHold || $lockedHold->status !== 'active' || $lockedHold->hold_until->isFuture()) {
                    return;
                }
                $product = Product::query()->lockForUpdate()->find($lockedHold->product_id);
                if (! $product || $product->availability_status !== ProductAvailabilityStatus::HELD->value) {
                    $lockedHold->update(['status' => 'expired', 'released_at' => now(), 'release_reason' => 'hold_expired_without_product_release']);

                    return;
                }
                if ((int) $product->held_by_transaction_id !== (int) $lockedHold->source_id) {
                    $lockedHold->update(['status' => 'expired', 'released_at' => now(), 'release_reason' => 'hold_owner_changed']);

                    return;
                }
                $from = $product->availability_status;
                $product->forceFill([
                    'availability_status' => ProductAvailabilityStatus::AVAILABLE->value,
                    'held_by_transaction_id' => null,
                    'hold_expires_at' => null,
                    'availability_version' => ((int) $product->availability_version) + 1,
                    'unavailable_reason' => null,
                ])->save();
                $lockedHold->update(['status' => 'expired', 'released_at' => now(), 'release_reason' => 'expired']);
                $this->history($product, $from, ProductAvailabilityStatus::AVAILABLE->value, $lockedHold->customer_id, $lockedHold->source, null, 'Tự động nhả giữ chỗ hết hạn.');
                $expired++;
            });
        });

        return $expired;
    }

    private function assertVersion(Product $product, ?int $expectedVersion): void
    {
        if ($expectedVersion !== null && $expectedVersion !== (int) $product->availability_version) {
            throw ValidationException::withMessages(['availability_version' => 'Trạng thái sản phẩm đã thay đổi. Hãy tải lại dữ liệu trước khi tiếp tục.']);
        }
    }

    private function assertTransition(string $from, string $to): void
    {
        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw ValidationException::withMessages(['availability_status' => "Không thể chuyển trạng thái sản phẩm từ {$from} sang {$to}."]);
        }
    }

    private function assertSourceOwnership(Product $product, ?Model $source, bool $adminOverride): void
    {
        $active = ProductHold::query()->where('product_id', $product->id)->where('status', 'active')->lockForUpdate()->first();
        if (! $active || $adminOverride) {
            return;
        }
        if (! $source || $active->source_type !== $source->getMorphClass() || (int) $active->source_id !== (int) $source->getKey()) {
            throw ValidationException::withMessages(['product' => 'Nguồn nghiệp vụ hiện tại không sở hữu lượt giữ chỗ của sản phẩm.']);
        }
    }

    private function closeActiveHold(Product $product, string $reason): void
    {
        ProductHold::query()->where('product_id', $product->id)->where('status', 'active')->update([
            'status' => 'released',
            'released_at' => now(),
            'release_reason' => $reason,
        ]);
    }

    private function history(Product $product, string $from, string $to, ?int $customerId, ?Model $source, $holdUntil, ?string $note): void
    {
        ProductAvailabilityHistory::query()->create([
            'product_id' => $product->id,
            'from_status' => $from,
            'to_status' => $to,
            'customer_id' => $customerId,
            'hold_until' => $holdUntil,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'note' => $note,
            'changed_by' => function_exists('user_id') ? user_id() : null,
        ]);
    }
}
