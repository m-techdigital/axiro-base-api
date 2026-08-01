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
    public function hold(Product $product, int $customerId, Model $source, int $minutes = 30, ?string $note = null): Product
    {
        return DB::transaction(function () use ($product, $customerId, $source, $minutes, $note) {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            if ($locked->availability_status !== ProductAvailabilityStatus::AVAILABLE->value) {
                throw ValidationException::withMessages(['product' => 'Sản phẩm không còn khả dụng.']);
            }

            $until = now()->addMinutes($minutes);
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

    public function transition(Product $product, ProductAvailabilityStatus|string $to, ?Model $source = null, ?string $note = null): Product
    {
        $to = $to instanceof ProductAvailabilityStatus ? $to->value : $to;

        return DB::transaction(function () use ($product, $to, $source, $note) {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            $from = $locked->availability_status;
            if ($from === $to) {
                return $locked;
            }

            $locked->forceFill([
                'availability_status' => $to,
                'held_by_transaction_id' => in_array($to, ['held', 'transacting'], true) ? $source?->getKey() : null,
                'hold_expires_at' => $to === 'held' ? $locked->hold_expires_at : null,
                'availability_version' => ((int) $locked->availability_version) + 1,
                'unavailable_reason' => $note,
            ])->save();

            if (! in_array($to, ['held', 'transacting'], true)) {
                ProductHold::query()->where('product_id', $locked->id)->where('status', 'active')->update([
                    'status' => 'released',
                    'released_at' => now(),
                    'release_reason' => $note,
                ]);
            }

            $this->history($locked, $from, $to, null, $source, $locked->hold_expires_at, $note);

            return $locked->fresh();
        });
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
