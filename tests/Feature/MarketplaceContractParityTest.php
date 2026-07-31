<?php

namespace Tests\Feature;

use Tests\TestCase;

class MarketplaceContractParityTest extends TestCase
{
    public function test_marketplace_contract_exposes_customer_avatar_endpoint(): void
    {
        $this->getJson('/api/v1/marketplace-contract')
            ->assertOk()
            ->assertJsonFragment([
                'POST /customer/profile/avatar',
            ]);
    }
}
