<?php

namespace App\Http\Controllers;

use App\Models\CustomerRefreshToken;
use App\Models\MarketplaceReview;
use App\Models\NotificationPreference;
use App\Models\Product;
use App\Models\ProductFavorite;
use App\Models\SavedSearch;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketplaceTrustController extends Controller
{
    public function favorites(Request $r)
    {
        $id = auth('customer_api')->id();
        $q = ProductFavorite::with(['product.owner:id,code,name,avatar_url', 'product.rentalRates'])->where('customer_id', $id)->latest();

        return success_response($q->paginate(min(100, max(1, $r->integer('per_page', 24)))));
    }

    public function favorite(Product $product)
    {
        $id = auth('customer_api')->id();
        $item = ProductFavorite::firstOrCreate(['customer_id' => $id, 'product_id' => $product->id]);

        return success_response($item, 'Đã lưu sản phẩm.', 201);
    }

    public function unfavorite(Product $product)
    {
        ProductFavorite::where('customer_id', auth('customer_api')->id())->where('product_id', $product->id)->delete();

        return success_response(null, 'Đã bỏ lưu sản phẩm.');
    }

    public function savedSearches()
    {
        return success_response(SavedSearch::where('customer_id', auth('customer_api')->id())->latest()->get());
    }

    public function storeSavedSearch(Request $r)
    {
        $d = $r->validate(['name' => 'required|string|max:120', 'filters' => 'required|array', 'notify' => 'nullable|boolean']);

        return success_response(SavedSearch::create([...$d, 'customer_id' => auth('customer_api')->id(), 'notify' => $d['notify'] ?? true]), 'Đã lưu bộ lọc.', 201);
    }

    public function deleteSavedSearch(SavedSearch $savedSearch)
    {
        abort_unless($savedSearch->customer_id === auth('customer_api')->id(), 403);
        $savedSearch->delete();

        return success_response(null, 'Đã xóa bộ lọc đã lưu.');
    }

    public function reviews(Request $r)
    {
        $id = auth('customer_api')->id();
        $q = MarketplaceReview::with(['transaction:id,code', 'reviewer:id,code,name', 'reviewee:id,code,name'])->where(fn ($q) => $q->where('reviewer_customer_id', $id)->orWhere('reviewee_customer_id', $id))->latest();

        return success_response($q->paginate(min(100, max(1, $r->integer('per_page', 20)))));
    }

    public function storeReview(Request $r, Transaction $transaction)
    {
        $id = auth('customer_api')->id();
        abort_unless(in_array($id, [$transaction->buyer_customer_id, $transaction->seller_customer_id], true), 403);
        if ($transaction->status !== 'completed') {
            throw ValidationException::withMessages(['transaction' => 'Chỉ có thể đánh giá sau khi giao dịch hoàn tất.']);
        }$d = $r->validate(['rating' => 'required|integer|min:1|max:5', 'criteria' => 'nullable|array', 'criteria.accuracy' => 'nullable|integer|min:1|max:5', 'criteria.speed' => 'nullable|integer|min:1|max:5', 'criteria.support' => 'nullable|integer|min:1|max:5', 'comment' => 'nullable|string|max:2000']);
        $reviewee = $id === $transaction->buyer_customer_id ? $transaction->seller_customer_id : $transaction->buyer_customer_id;
        $item = MarketplaceReview::updateOrCreate(['transaction_id' => $transaction->id, 'reviewer_customer_id' => $id], [...$d, 'product_id' => $transaction->product_id, 'reviewee_customer_id' => $reviewee, 'status' => 'published']);

        return success_response($item->fresh(['reviewer:id,code,name', 'reviewee:id,code,name']), 'Đã lưu đánh giá.');
    }

    public function preferences()
    {
        $id = auth('customer_api')->id();
        $categories = ['transaction', 'payment', 'handover', 'rental_due', 'document', 'case', 'product', 'security', 'marketing'];
        foreach ($categories as $category) {
            NotificationPreference::firstOrCreate(['customer_id' => $id, 'category' => $category], ['in_app' => true, 'email' => in_array($category, ['security', 'transaction', 'payment'], true), 'push' => false]);
        }

        return success_response(NotificationPreference::where('customer_id', $id)->orderBy('category')->get());
    }

    public function updatePreferences(Request $r)
    {
        $items = $r->validate(['items' => 'required|array', 'items.*.category' => 'required|in:transaction,payment,handover,rental_due,document,case,listing,security,marketing', 'items.*.in_app' => 'required|boolean', 'items.*.email' => 'required|boolean', 'items.*.push' => 'required|boolean'])['items'];
        $id = auth('customer_api')->id();
        DB::transaction(function () use ($items, $id) {
            foreach ($items as $item) {
                if ($item['category'] === 'security') {
                    $item['in_app'] = true;
                    $item['email'] = true;
                }NotificationPreference::updateOrCreate(['customer_id' => $id, 'category' => $item['category']], $item);
            }
        });

        return $this->preferences();
    }

    public function sessions()
    {
        return success_response(CustomerRefreshToken::where('customer_id', auth('customer_api')->id())->whereNull('revoked_at')->where('expires_at', '>', now())->latest()->get(['id', 'ip_address', 'user_agent', 'last_used_at', 'created_at', 'expires_at']));
    }

    public function revokeSession(CustomerRefreshToken $session)
    {
        abort_unless($session->customer_id === auth('customer_api')->id(), 403);
        $session->update(['revoked_at' => now()]);

        return success_response(null, 'Đã thu hồi phiên đăng nhập.');
    }
}
