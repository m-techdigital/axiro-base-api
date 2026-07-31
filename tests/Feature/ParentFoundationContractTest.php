<?php
namespace Tests\Feature;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
class ParentFoundationContractTest extends TestCase {
 #[Test] public function api_uses_parent_aligned_response_validation_and_resource_owners(): void {
  foreach (['app/Http/Responses/ApiResponse.php','app/Support/Http/PaginationMeta.php','app/Http/Requests/ApiFormRequest.php','app/Http/Resources/ApiResource.php','docs/canonical/README.md'] as $file) $this->assertFileExists(base_path($file));
  $helpers=file_get_contents(app_path('Helpers/helpers.php')); $this->assertStringContainsString('ApiResponse::success', $helpers); $this->assertStringContainsString('ApiResponse::error', $helpers);
 }
 #[Test] public function selected_customer_and_admin_controllers_do_not_own_inline_validation(): void {
  foreach (['CustomerController.php','CustomerWalletController.php','CustomerTransactionController.php','ListingController.php'] as $file) {
   $source=file_get_contents(app_path('Http/Controllers/'.$file)); $this->assertStringNotContainsString('->validate([', $source, $file);
  }
 }
}
