<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BaseListQueryParityTest extends TestCase
{
    #[Test]
    public function shared_list_query_supports_admin_filter_sort_and_pagination_contracts(): void
    {
        $request = file_get_contents(app_path('Http/Requests/Common/ListQueryRequest.php'));
        $trait = file_get_contents(app_path('Support/Query/AppliesListQuery.php'));

        foreach (['keyword', 'status', 'product_type', 'offer_mode', 'payment_type', 'transaction_id', 'customer_id', 'date_from', 'date_to', 'sort_by', 'sort_direction', 'per_page'] as $field) {
            $this->assertStringContainsString("'{$field}'", $request);
        }

        $this->assertStringContainsString("'max:100'", $request);
        $this->assertStringContainsString('whereDate', $trait);
        $this->assertStringContainsString('in_array($sortBy', $trait);

        foreach (['PaymentController.php', 'DisputeController.php', 'WalletAdminController.php'] as $controller) {
            $source = file_get_contents(app_path("Http/Controllers/{$controller}"));
            $this->assertStringContainsString('ListQueryRequest', $source);
            $this->assertStringContainsString('applyListFilters', $source);
        }
    }
}
