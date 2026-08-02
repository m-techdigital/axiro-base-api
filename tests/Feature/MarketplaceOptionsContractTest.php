<?php

namespace Tests\Feature;

use App\Enums\DisputeOutcome;
use App\Support\Marketplace\DocumentType;
use Tests\TestCase;

class MarketplaceOptionsContractTest extends TestCase
{
    public function test_marketplace_options_expose_canonical_document_types_and_dispute_outcomes(): void
    {
        $this->getJson('/api/v1/marketplace/options')
            ->assertOk()
            ->assertJsonPath('data.document_types.0.value', DocumentType::SALE_RECORD)
            ->assertJsonFragment(['value' => DocumentType::RENTAL_RECORD])
            ->assertJsonFragment(['value' => DisputeOutcome::CANCEL_REFUND->value]);
    }
}
