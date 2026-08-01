<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['rentalRates', 'offerModes'])->where('owner_customer_id', auth('customer_api')->id());
        if ($request->filled('game_code')) {
            $query->where('game_code', $request->string('game_code'));
        }
        if ($request->filled('product_type')) {
            $query->where('product_type', $request->string('product_type'));
        }

        return success_response($query->latest()->paginate(min(100, max(1, $request->integer('per_page', 20)))));
    }

    public function store(ProductRequest $request)
    {
        $data = $request->validated();

        return DB::transaction(function () use ($data) {
            $rates = $data['rental_rates'] ?? [];
            $modes = $data['offer_modes'];
            unset($data['rental_rates'], $data['offer_modes']);
            $data['code'] ??= 'PRD-'.strtoupper(Str::random(8));
            $data['slug'] ??= Str::slug($data['name'].'-'.$data['code']);
            $data['owner_customer_id'] = auth('customer_api')->id();
            $data['status'] = 'active';
            $data['approval_status'] = 'pending';
            $data['is_published'] = false;
            $product = Product::create($data);
            $product->syncOfferModes($modes);
            $this->syncRates($product, $rates);

            return success_response($product->load(['rentalRates', 'offerModes']), 'Đã gửi sản phẩm để duyệt', 201);
        });
    }

    public function update(ProductRequest $request, Product $product)
    {
        abort_unless($product->owner_customer_id === auth('customer_api')->id(), 403);
        abort_if(in_array($product->availability_status, ['held', 'transacting', 'rented', 'sold'], true), 422, 'Không thể sửa sản phẩm đang có giao dịch.');
        $data = $request->validated();

        return DB::transaction(function () use ($data, $product) {
            $rates = $data['rental_rates'] ?? null;
            $modes = $data['offer_modes'];
            unset($data['rental_rates'], $data['offer_modes']);
            $data['approval_status'] = 'pending';
            $data['is_published'] = false;
            $data['approved_at'] = null;
            $data['approved_by'] = null;
            $data['rejection_reason'] = null;
            $product->update($data);
            $product->syncOfferModes($modes);
            if ($rates !== null) {
                $this->syncRates($product, $rates);
            }

            return success_response($product->fresh()->load(['rentalRates', 'offerModes']));
        });
    }

    private function syncRates(Product $product, array $rates): void
    {
        $product->rentalRates()->delete();
        foreach (array_values($rates) as $i => $rate) {
            $product->rentalRates()->create($rate + ['sort_order' => $i]);
        }
    }
}
