<?php

namespace Tests\Feature;

use App\Http\Requests\Customer\LoginCustomerRequest;
use App\Http\Responses\ApiExceptionResponse;
use App\Support\CorrelationContext;
use App\Support\SecurityPasswordRules;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ParentDeepFoundationContractTest extends TestCase
{
    #[Test]
    public function mini_keeps_lightweight_parent_foundations_without_parent_domains(): void
    {
        $this->assertTrue(class_exists(LoginCustomerRequest::class));
        $this->assertTrue(class_exists(ApiExceptionResponse::class));
        $this->assertTrue(class_exists(CorrelationContext::class));
        $this->assertTrue(class_exists(SecurityPasswordRules::class));

        $bootstrap = file_get_contents(base_path('bootstrap/app.php'));
        $this->assertStringContainsString('ApiExceptionResponse::unauthenticated', $bootstrap);
        $this->assertStringContainsString('ApiExceptionResponse::validation', $bootstrap);

        $controller = file_get_contents(app_path('Http/Controllers/CustomerAuthController.php'));
        $this->assertStringContainsString('LoginCustomerRequest $request', $controller);

        foreach (['Companies', 'Projects', 'Departments', 'Accounting', 'Payroll'] as $excludedDomain) {
            $this->assertDirectoryDoesNotExist(app_path($excludedDomain));
        }
    }
}
