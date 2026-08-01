<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ParentBaseParityContractTest extends TestCase
{
    #[Test]
    public function list_query_supports_parent_compatible_aliases_and_safe_extensions(): void
    {
        $request = file_get_contents(app_path('Http/Requests/Common/ListQueryRequest.php'));
        $trait = file_get_contents(app_path('Support/Query/AppliesListQuery.php'));

        $this->assertStringContainsString("input('q')", $request);
        $this->assertStringContainsString("input('limit')", $request);
        $this->assertStringContainsString("input('sort'", $request);
        $this->assertStringContainsString('public function filters(array $allowed)', $request);
        $this->assertStringContainsString('array $customFilters = []', $trait);
        $this->assertStringContainsString('$request->sortBy($defaultSort)', $trait);
    }
}
