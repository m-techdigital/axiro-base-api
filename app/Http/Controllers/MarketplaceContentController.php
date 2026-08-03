<?php

namespace App\Http\Controllers;

use App\Models\ContentEntry;
use App\Models\MarketplaceReview;
use App\Models\Product;
use Illuminate\Http\Request;

class MarketplaceContentController extends Controller
{
    public function index(Request $r)
    {
        $q = ContentEntry::where('status', 'published')->where(fn ($q) => $q->whereNull('effective_at')->orWhere('effective_at', '<=', now()))->when($r->type, fn ($q, $v) => $q->where('type', $v))->latest('published_at');

        return success_response($q->paginate(min(100, max(1, $r->integer('per_page', 20)))));
    }

    public function show(ContentEntry $contentEntry)
    {
        abort_unless($contentEntry->status === 'published' && (! $contentEntry->effective_at || $contentEntry->effective_at->lte(now())), 404);

        return success_response($contentEntry);
    }

    public function bySlug(string $slug)
    {
        $item = ContentEntry::where('slug', $slug)->where('status', 'published')->where(fn ($q) => $q->whereNull('effective_at')->orWhere('effective_at', '<=', now()))->firstOrFail();

        return success_response($item);
    }

    public function productReviews(Product $product, Request $r)
    {
        $q = MarketplaceReview::with('reviewer:id,code,name,avatar_url')->where('product_id', $product->id)->where('status', 'published')->latest();
        $summary = ['average' => (float) ($q->clone()->avg('rating') ?? 0), 'count' => $q->clone()->count()];

        return success_response(['summary' => $summary, 'reviews' => $q->paginate(min(50, max(1, $r->integer('per_page', 10))))]);
    }
}
