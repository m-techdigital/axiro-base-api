<?php
namespace Tests\Feature;

use App\Models\Customer;
use App\Models\GeneratedDocument;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\MarketplaceDocumentSeeder;
use Database\Seeders\MarketplaceDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceDocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_customers_share_the_same_generated_document(): void
    {
        User::factory()->create(['username'=>'admin','password'=>'change-me']);
        $this->seed(MarketplaceDemoSeeder::class);
        $this->seed(MarketplaceDocumentSeeder::class);

        $transaction = Transaction::where('code','TRX-DEMO-COMPLETED-SALE')->firstOrFail();
        $document = GeneratedDocument::where('transaction_id',$transaction->id)->where('document_type','sale_contract')->firstOrFail();
        $buyer = Customer::findOrFail($transaction->buyer_customer_id);
        $seller = Customer::findOrFail($transaction->seller_customer_id);

        $buyerToken = auth('customer_api')->login($buyer);
        $this->withHeader('Authorization','Bearer '.$buyerToken)
            ->getJson("/api/v1/customer/transactions/{$transaction->id}/documents")
            ->assertOk()
            ->assertJsonFragment(['document_type'=>'sale_contract']);
        $this->withHeader('Authorization','Bearer '.$buyerToken)
            ->postJson("/api/v1/customer/documents/{$document->id}/accept", [
                'accepted_terms' => true,
                'acceptance_statement' => 'Tôi đã đọc, hiểu và đồng ý với toàn bộ nội dung tài liệu.',
            ])
            ->assertOk();

        $sellerToken = auth('customer_api')->login($seller);
        $this->withHeader('Authorization','Bearer '.$sellerToken)
            ->postJson("/api/v1/customer/documents/{$document->id}/accept", [
                'accepted_terms' => true,
                'acceptance_statement' => 'Tôi đã đọc, hiểu và đồng ý với toàn bộ nội dung tài liệu.',
            ])
            ->assertOk();

        $this->assertDatabaseCount('document_acceptances',2);
        $this->assertStringContainsString($transaction->code,$document->rendered_html);
    }
}
