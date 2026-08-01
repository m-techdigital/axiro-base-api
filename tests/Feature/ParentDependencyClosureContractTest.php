<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ParentDependencyClosureContractTest extends TestCase
{
    #[Test]
    public function mini_foundations_are_explicitly_bounded_and_do_not_pull_parent_scope_dependencies(): void
    {
        $manifestPath = base_path('docs/canonical/parent-base-provenance.json');
        $this->assertFileExists($manifestPath);

        $manifest = json_decode(
            (string) file_get_contents($manifestPath),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $entries = collect($manifest['entries'] ?? [])->keyBy('mini_path');

        $this->assertSame(
            'mini_bounded',
            $entries->get('app/Repositories/BaseRepository.php')['mode'] ?? null,
        );
        $this->assertSame(
            'mini_bounded',
            $entries->get('app/Support/Query/AppliesListQuery.php')['mode'] ?? null,
        );

        $repository = (string) file_get_contents(app_path('Repositories/BaseRepository.php'));
        $querySupport = (string) file_get_contents(app_path('Support/Query/AppliesListQuery.php'));

        $this->assertStringNotContainsString('company_id', $repository);
        $this->assertStringNotContainsString('scopeAllowedRelation', $repository);
        $this->assertStringNotContainsString('request()->all()', $querySupport);
        $this->assertStringContainsString('ListQueryRequest', $querySupport);

        foreach ([
            'app/Models/Traits/Filterable.php',
            'app/Repositories/BaseRepositoryInterface.php',
            'app/Services/Accounting',
            'app/Services/Reports',
            'app/Services/HrOperations',
        ] as $path) {
            $absolutePath = base_path($path);

            if (str_ends_with($path, '.php')) {
                $this->assertFileDoesNotExist($absolutePath);
                continue;
            }

            $this->assertDirectoryDoesNotExist($absolutePath);
        }
    }
}
