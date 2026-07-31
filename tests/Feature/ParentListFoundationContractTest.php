<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ParentListFoundationContractTest extends TestCase
{
    #[Test]
    public function list_query_and_filter_owners_match_the_parent_foundation(): void
    {
        $this->assertFileExists(app_path('Http/Requests/Common/ListQueryRequest.php'));
        $this->assertFileExists(app_path('Support/Query/AppliesListQuery.php'));

        $request = file_get_contents(app_path('Http/Requests/Common/ListQueryRequest.php'));
        $trait = file_get_contents(app_path('Support/Query/AppliesListQuery.php'));

        $this->assertStringContainsString("'per_page'", $request);
        $this->assertStringContainsString("'sort_by'", $request);
        $this->assertStringContainsString('applyListFilters', $trait);
        $this->assertStringContainsString('sortableColumns', $trait);
    }
}
