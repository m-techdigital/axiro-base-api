<?php

namespace App\Services;

use App\Enums\OfferModeCode;
use App\Enums\ProductAvailabilityStatus;
use App\Enums\ProductSelectionContext;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class ProductSelectionService
{
    public function apply(Builder $query, ProductSelectionContext|string $context, ?string $offerMode = null): Builder
    {
        $context = $context instanceof ProductSelectionContext ? $context : ProductSelectionContext::from($context);

        if ($context === ProductSelectionContext::MANAGEMENT || $context === ProductSelectionContext::ADMIN_REVIEW) {
            return $query;
        }

        $query->where('approval_status', 'approved');

        if ($context === ProductSelectionContext::PUBLIC_MARKETPLACE) {
            $query->where('is_published', true)->where('availability_status', ProductAvailabilityStatus::AVAILABLE->value);
        }

        if ($context === ProductSelectionContext::TRANSACTION) {
            $query->where('is_published', true)->where('availability_status', ProductAvailabilityStatus::AVAILABLE->value);
        }

        if ($context === ProductSelectionContext::RENTAL) {
            $query->where('is_published', true)->whereIn('availability_status', [ProductAvailabilityStatus::AVAILABLE->value]);
            $offerMode = OfferModeCode::RENT->value;
        }

        if ($offerMode) {
            $query->whereHas('offerModes', fn (Builder $mode) => $mode->where('code', $offerMode));
        }

        return $query;
    }

    public function assertSelectable(Product $product, ProductSelectionContext|string $context, ?string $offerMode = null): void
    {
        $exists = $this->apply(Product::query()->whereKey($product->getKey()), $context, $offerMode)->exists();
        if (! $exists) {
            throw ValidationException::withMessages(['product' => 'Sản phẩm không còn phù hợp với ngữ cảnh giao dịch đã chọn.']);
        }
    }
}
