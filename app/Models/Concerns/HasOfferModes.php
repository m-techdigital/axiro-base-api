<?php

namespace App\Models\Concerns;

use App\Enums\OfferModeCode;
use App\Models\OfferMode;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasOfferModes
{
    public function offerModes(): MorphToMany
    {
        return $this->morphToMany(OfferMode::class, 'model', 'model_offer_modes', 'model_id', 'offer_mode_id');
    }

    public function syncOfferModes(?array $codes): void
    {
        if ($codes === null) {
            return;
        }

        $codes = array_values(array_unique($codes));
        foreach ($codes as $index => $code) {
            $enum = OfferModeCode::tryFrom($code);
            OfferMode::query()->firstOrCreate(['code' => $code], ['name' => $enum?->label() ?? $code, 'is_active' => true, 'sort_order' => ($index + 1) * 10]);
        }
        $ids = OfferMode::query()->whereIn('code', $codes)->pluck('id')->all();
        $this->offerModes()->sync($ids);
        $this->unsetRelation('offerModes');
    }

    public function offerModeCodes(): array
    {
        if ($this->relationLoaded('offerModes')) {
            return $this->getRelation('offerModes')
                ->pluck('code')
                ->values()
                ->all();
        }

        return $this->offerModes()->pluck('code')->all();
    }
}
