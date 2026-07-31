<?php

namespace App\Http\Controllers;

use App\Models\ProductListing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class MarketplaceController extends Controller
{
    public function index(Request $request)
    {
        $query = ProductListing::query()
            ->with(['product', 'owner:id,code,name,avatar_url', 'rentalRates'])
            ->where('status', 'published');

        if ($request->filled('listing_type')) {
            $query->where('listing_type', $request->string('listing_type'));
        }

        if ($request->filled('product_type')) {
            $productType = $request->string('product_type')->toString();
            $query->whereHas('product', fn (Builder $product) => $product
                ->where('product_type', $productType)
                ->orWhere('game_code', $productType));
        }

        if ($request->filled('game_code')) {
            $query->whereHas('product', fn (Builder $product) => $product
                ->where('game_code', $request->string('game_code')));
        }

        if ($request->filled('code')) {
            $query->where('code', 'like', '%'.$request->string('code').'%');
        }

        if ($request->filled('username')) {
            $identity = $request->string('username')->toString();
            $query->whereHas('product', fn (Builder $product) => $product
                ->where('name', 'like', "%{$identity}%")
                ->orWhere('code', 'like', "%{$identity}%")
                ->orWhere('attributes->character_name', 'like', "%{$identity}%"));
        }

        if ($request->filled('keyword')) {
            $keyword = $request->string('keyword')->toString();
            $query->where(fn (Builder $listing) => $listing
                ->where('title', 'like', "%{$keyword}%")
                ->orWhere('code', 'like', "%{$keyword}%")
                ->orWhereHas('product', fn (Builder $product) => $product
                    ->where('name', 'like', "%{$keyword}%")));
        }

        if ($request->filled('price')) {
            $price = preg_replace('/[^0-9]/', '', $request->string('price')->toString());
            if ($price !== '') {
                $query->where(fn (Builder $listing) => $listing
                    ->where('sale_price', '<=', (int) $price)
                    ->orWhere('rental_price', '<=', (int) $price));
            }
        }

        if ($request->filled('level')) {
            $query->whereHas('product', fn (Builder $product) => $product
                ->where('level', 'like', '%'.$request->string('level').'%'));
        }

        if ($request->filled('server')) {
            $query->whereHas('product', fn (Builder $product) => $product
                ->where('server_name', 'like', '%'.$request->string('server').'%'));
        }

        foreach (['class', 'planet', 'land', 'sex'] as $attribute) {
            if ($request->filled($attribute)) {
                $query->whereHas('product', fn (Builder $product) => $product
                    ->where("attributes->{$attribute}", $request->string($attribute)->toString()));
            }
        }

        $perPage = max(1, min($request->integer('per_page', 20), 60));

        return success_response($query->latest('id')->paginate($perPage));
    }

    public function show(string $listingCode)
    {
        $listing = ProductListing::query()
            ->where('code', $listingCode)
            ->orWhere('id', $listingCode)
            ->firstOrFail();

        abort_unless(
            in_array($listing->status, ['published', 'reserved'], true) || auth('customer_api')->id() === $listing->owner_customer_id,
            404
        );

        return success_response($listing->load(['product', 'owner:id,code,name,avatar_url', 'rentalRates']));
    }
}
