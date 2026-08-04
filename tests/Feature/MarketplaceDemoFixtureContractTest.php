<?php

namespace Tests\Feature;

use App\Models\ContentEntry;
use App\Models\Customer;
use App\Models\CustomerPayoutAccount;
use App\Models\CustomerVerification;
use App\Models\CustomerWallet;
use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Models\MarketplaceReview;
use App\Models\MarketplaceRiskFlag;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Database\Seeders\MarketplaceDemoSeeder;
use Database\Seeders\MarketplaceDocumentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceDemoFixtureContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_fixture_is_stable_for_browser_and_transactional_e2e(): void
    {
        User::factory()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'change-me',
        ]);

        $this->seed(MarketplaceDemoSeeder::class);
        $this->seed(MarketplaceDocumentSeeder::class);

        foreach (['customer', 'seller', 'renter', 'lessor', 'dispute'] as $username) {
            $customer = Customer::query()->where('username', $username)->firstOrFail();
            $this->assertSame('active', $customer->status);
            $this->assertNotNull(CustomerWallet::query()->where('customer_id', $customer->id)->first());
            $this->assertSame('verified', CustomerVerification::query()->where('customer_id', $customer->id)->value('status'));
            $this->assertSame('verified', CustomerPayoutAccount::query()->where('customer_id', $customer->id)->where('is_default', true)->value('status'));
        }

        $this->assertDatabaseHas('products', [
            'code' => 'NSO-0102',
            'approval_status' => 'approved',
            'is_published' => true,
            'availability_status' => 'available',
        ]);
        $this->assertDatabaseHas('products', [
            'code' => 'NSO-0201',
            'approval_status' => 'approved',
            'is_published' => true,
            'availability_status' => 'available',
        ]);
        $this->assertDatabaseHas('products', [
            'code' => 'NRO-0301',
            'approval_status' => 'approved',
            'is_published' => true,
            'installment_enabled' => true,
            'availability_status' => 'available',
        ]);

        foreach ([
            'TRX-DEMO-INSTALLMENT',
            'TRX-DEMO-COMPLETED-SALE',
            'TRX-DEMO-ACTIVE-RENTAL',
            'TRX-DEMO-RETURNED-RENTAL',
            'TRX-DEMO-DISPUTE-OPEN',
            'TRX-DEMO-CANCELLED',
        ] as $code) {
            $this->assertTrue(Transaction::query()->where('code', $code)->exists(), $code);
        }

        $this->assertSame('submitted', WithdrawalRequest::query()->where('idempotency_key', 'demo-withdrawal-submitted')->value('status'));
        $this->assertSame('paid', WithdrawalRequest::query()->where('idempotency_key', 'demo-withdrawal-paid')->value('status'));
        $this->assertSame(3, Product::query()->whereIn('code', ['NSO-0102', 'NSO-0201', 'NRO-0301'])->count());
        $this->assertDatabaseHas('marketplace_risk_flags', [
            'code' => 'RISK-DEMO-HIGH',
            'level' => 'high',
            'status' => 'reviewing',
        ]);
        $this->assertDatabaseHas('marketplace_risk_flags', [
            'code' => 'RISK-DEMO-RESOLVED',
            'status' => 'resolved',
        ]);
        $this->assertSame(2, MarketplaceRiskFlag::query()->where('code', 'like', 'RISK-DEMO-%')->count());
        $this->assertDatabaseHas('content_entries', [
            'slug' => 'demo-chinh-sach-giao-dich-an-toan',
            'status' => 'published',
        ]);
        $this->assertDatabaseHas('content_entries', [
            'slug' => 'demo-huong-dan-xu-ly-tranh-chap',
            'status' => 'draft',
        ]);
        $this->assertSame(2, ContentEntry::query()->where('slug', 'like', 'demo-%')->count());
        $this->assertSame(1, MarketplaceReview::query()->where('comment', 'Giao dịch demo hoàn tất đúng cam kết.')->count());
        $this->assertGreaterThan(0, GeneratedDocument::query()->whereHas('transaction', fn ($query) => $query->where('code', 'like', 'TRX-DEMO-%'))->count());
        $issuedTemplate = DocumentTemplate::query()
            ->withCount('generatedDocuments')
            ->where('status', 'published')
            ->whereHas('generatedDocuments')
            ->first();
        $this->assertNotNull($issuedTemplate, 'Fresh demo seed phải có ít nhất một document template đã phát hành tài liệu.');
    }
}
