<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ParentSourceSelectionContractTest extends TestCase
{
    #[Test]
    public function parent_source_selection_is_explicit_and_heavy_domains_are_not_ported(): void
    {
        $manifestPath = base_path('docs/canonical/parent-base-provenance.json');
        $this->assertFileExists($manifestPath);

        $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertNotEmpty($manifest['entries'] ?? []);
        $this->assertContains('mini_bounded', array_column($manifest['entries'], 'mode'));

        foreach ([
            'app/Services/Accounting',
            'app/Services/Reports',
            'app/Services/HrOperations',
            'app/Services/Approvals',
            'app/Services/Calendar',
        ] as $path) {
            $this->assertDirectoryDoesNotExist(base_path($path));
        }
    }
}
