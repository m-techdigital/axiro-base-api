<?php

namespace Tests\Feature;

use App\Enums\OfferModeCode;
use App\Models\Product;
use Database\Seeders\OfferModeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParentOfferModeArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_uses_parent_aligned_offer_modes_and_installment_is_separate(): void
    {
        $this->seed(OfferModeSeeder::class);
        $product = Product::query()->create(['code' => 'PRD-OFFER-1', 'name' => 'Offer test', 'product_type' => 'game_account', 'game_code' => 'ninja_school', 'status' => 'active', 'installment_enabled' => true, 'sale_price' => 100000]);
        $product->syncOfferModes([OfferModeCode::SELL->value, OfferModeCode::RENT->value]);
        $freshProduct = $product->fresh()->load('offerModes');

        $this->assertSame(['rent', 'sell'], collect($freshProduct->offer_modes)->sort()->values()->all());
        $this->assertSame(['rent', 'sell'], collect($freshProduct->offerModeCodes())->sort()->values()->all());
        $this->assertTrue($freshProduct->supports('installment'));
        $this->assertDatabaseHas('model_offer_modes', ['model_id' => $product->id]);
    }
}
