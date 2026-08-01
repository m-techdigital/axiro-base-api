<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ParentParallelStructureTest extends TestCase
{
    #[Test]
    public function mini_api_keeps_parent_aligned_foundation_structure(): void
    {
        foreach ([
            app_path('Contracts/Http/ApiResponder.php'),
            app_path('Http/Responses/DefaultApiResponder.php'),
            app_path('Repositories/BaseRepository.php'),
            base_path('routes/api/auth.php'),
            base_path('routes/api/public.php'),
            base_path('routes/api/customer.php'),
            base_path('routes/api/admin.php'),
            base_path('tests/Support/CreatesMarketplaceFixtures.php'),
        ] as $file) {
            $this->assertFileExists($file);
        }

        $routes = file_get_contents(base_path('routes/api.php'));
        $this->assertStringContainsString('api/auth.php', $routes);
        $this->assertStringContainsString('api/customer.php', $routes);
        $this->assertStringContainsString('api/admin.php', $routes);
    }
}
