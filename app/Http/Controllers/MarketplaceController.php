<?php

namespace App\Http\Controllers;

use App\Enums\ProductSelectionContext;
use App\Models\Product;
use App\Services\ProductSelectionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class MarketplaceController extends Controller
{
    public function __construct(private ProductSelectionService $selection) {}

    public function index(Request $request)
    {
        $mode = $request->input('offer_mode') ?? $request->input('transaction_type');
        $mode = match ($mode) {
            'sale', 'purchase' => 'sell', 'rental' => 'rent', 'installment' => 'sell', default => $mode
        };
        $query = $this->selection->apply(Product::with(['owner:id,code,name,avatar_url', 'rentalRates', 'offerModes']), ProductSelectionContext::PUBLIC_MARKETPLACE, $mode);
        if ($request->input('transaction_type') === 'installment') {
            $query->where('installment_enabled', true);
        }
        if ($request->filled('product_type')) {
            $query->where('product_type', $request->string('product_type'));
        }
        if ($request->filled('game_code')) {
            $query->where('game_code', $request->string('game_code'));
        }
        if ($request->filled('keyword')) {
            $keyword = $request->string('keyword')->toString();
            $query->where(fn (Builder $q) => $q->where('name', 'like', "%{$keyword}%")->orWhere('code', 'like', "%{$keyword}%")->orWhere('server_name', 'like', "%{$keyword}%"));
        }
        if ($request->filled('price')) {
            $price = preg_replace('/[^0-9]/', '', $request->string('price')->toString());
            if ($price !== '') {
                $query->where(fn (Builder $q) => $q->where('sale_price', '<=', (int) $price)->orWhere('rental_price', '<=', (int) $price));
            }
        }
        if ($request->filled('level')) {
            $query->where('level', 'like', '%'.$request->string('level').'%');
        }
        if ($request->filled('server')) {
            $query->where('server_name', 'like', '%'.$request->string('server').'%');
        }
        foreach (['class', 'planet', 'land', 'sex'] as $attribute) {
            if ($request->filled($attribute)) {
                $query->where("attributes->{$attribute}", $request->string($attribute)->toString());
            }
        }

        return success_response($query->latest('id')->paginate(max(1, min($request->integer('per_page', 20), 60))));
    }

    public function show(string $productCode)
    {
        $product = Product::where('code', $productCode)->orWhere('id', $productCode)->firstOrFail();
        $isOwner = auth('customer_api')->id() === $product->owner_customer_id;
        abort_unless(($product->approval_status === 'approved' && $product->is_published) || $isOwner, 404);

        return success_response($product->load(['owner:id,code,name,avatar_url', 'rentalRates', 'offerModes']));
    }
}
