<?php

namespace Database\Seeders;

use App\Enums\OfferModeCode;
use App\Models\OfferMode;
use Illuminate\Database\Seeder;

class OfferModeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (OfferModeCode::cases() as $index => $case) {
            OfferMode::query()->updateOrCreate(['code' => $case->value], [
                'name' => $case->label(), 'is_active' => true, 'sort_order' => ($index + 1) * 10,
            ]);
        }
    }
}
