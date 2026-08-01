<?php

namespace Tests\Feature;

use App\Models\ContentEntry;
use App\Models\Customer;
use App\Models\MarketplaceReview;
use App\Models\NotificationPreference;
use App\Models\Product;
use App\Models\ProductFavorite;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceTrustGrowthTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_transaction_accepts_one_review_per_reviewer(): void
    {
        [$buyer,$seller,$listing,$transaction] = $this->fixture();
        $review = MarketplaceReview::updateOrCreate(['transaction_id' => $transaction->id, 'reviewer_customer_id' => $buyer->id], ['product_id' => $listing->id, 'reviewee_customer_id' => $seller->id, 'rating' => 5, 'comment' => 'Tốt', 'status' => 'published']);
        MarketplaceReview::updateOrCreate(['transaction_id' => $transaction->id, 'reviewer_customer_id' => $buyer->id], ['product_id' => $listing->id, 'reviewee_customer_id' => $seller->id, 'rating' => 4, 'comment' => 'Đã cập nhật', 'status' => 'published']);
        $this->assertSame(1, MarketplaceReview::count());
        $this->assertSame(4, $review->fresh()->rating);
    }

    public function test_listing_favorite_is_unique_per_customer(): void
    {
        [$buyer,,$listing] = $this->fixture();
        ProductFavorite::firstOrCreate(['customer_id' => $buyer->id, 'product_id' => $listing->id]);
        ProductFavorite::firstOrCreate(['customer_id' => $buyer->id, 'product_id' => $listing->id]);
        $this->assertSame(1, ProductFavorite::count());
    }

    public function test_published_content_is_available_through_public_contract(): void
    {
        ContentEntry::create(['type' => 'guide', 'slug' => 'an-toan-giao-dich', 'title' => 'An toàn giao dịch', 'body' => 'Nội dung', 'status' => 'published', 'published_at' => now()]);
        $this->getJson('/api/v1/content/slug/an-toan-giao-dich')->assertOk()->assertJsonPath('data.slug', 'an-toan-giao-dich');
    }

    public function test_security_preferences_cannot_be_disabled_by_persisted_default(): void
    {
        [$buyer] = $this->fixture();
        $item = NotificationPreference::create(['customer_id' => $buyer->id, 'category' => 'security', 'in_app' => true, 'email' => true, 'push' => false]);
        $this->assertTrue($item->in_app);
        $this->assertTrue($item->email);
    }

    private function fixture(): array
    {
        $buyer = Customer::create(['code' => 'CUS-T-B', 'username' => 'trustbuyer', 'name' => 'Buyer', 'email' => 'trustbuyer@example.com', 'phone' => '0910000001', 'password' => 'password', 'status' => 'active']);
        $seller = Customer::create(['code' => 'CUS-T-S', 'username' => 'trustseller', 'name' => 'Seller', 'email' => 'trustseller@example.com', 'phone' => '0910000002', 'password' => 'password', 'status' => 'active']);
        $product = Product::create(['code' => 'PRO-T', 'name' => 'Nick trust', 'product_type' => 'game_account', 'game_code' => 'ninja_school', 'owner_customer_id' => $seller->id, 'approval_status' => 'approved', 'is_published' => true, 'availability_status' => 'available', 'status' => 'active', 'sale_price' => 100000, 'published_at' => now()]);
        $transaction = Transaction::create(['code' => 'TRX-T', 'transaction_type' => 'purchase', 'purchase_mode' => 'full',
            'product_id' => $product->id, 'buyer_customer_id' => $buyer->id, 'seller_customer_id' => $seller->id, 'transaction_value' => 100000, 'total_payable' => 100000, 'seller_net_amount' => 100000, 'transaction_date' => now()->toDateString(), 'status' => 'completed']);

        return [$buyer, $seller, $product, $transaction];
    }
}
