<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\ProductRejectRequest;
use App\Http\Requests\Common\ListQueryRequest;
use App\Http\Requests\ProductRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Product;
use App\Services\Marketplace\MarketplaceNotificationService;
use App\Support\Query\AppliesListQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    use AppliesListQuery;

    public function index(ListQueryRequest $request)
    {
        $query = $this->applyListFilters(Product::with(['owner:id,code,name', 'rentalRates', 'offerModes']), $request,
            ['code', 'name', 'server_name'], ['status', 'approval_status', 'game_code', 'product_type', 'availability_status', 'availability_version'],
            ['id', 'code', 'name', 'game_code', 'product_type', 'status', 'approval_status', 'availability_status', 'availability_version', 'created_at']);
        $mode = $request->input('offer_mode') ?? $request->input('transaction_type');
        if ($mode) {
            $mode = match ($mode) {
                'sale', 'purchase' => 'sell', 'rental' => 'rent', default => $mode
            };
            if ($mode === 'installment') {
                $query->where('installment_enabled', true)->whereHas('offerModes', fn ($q) => $q->where('code', 'sell'));
            } else {
                $query->whereHas('offerModes', fn ($q) => $q->where('code', $mode));
            }
        }

        return ApiResponse::paginated($query->paginate($request->perPage()));
    }

    public function store(ProductRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $data = $request->validated();
            $rates = $data['rental_rates'] ?? [];
            $modes = $data['offer_modes'];
            unset($data['rental_rates'], $data['offer_modes']);
            $product = Product::create($this->prepare($data, true));
            $product->syncOfferModes($modes);
            $this->syncRates($product, $rates);

            return ApiResponse::success($product->load(['rentalRates', 'offerModes']), 'Đã tạo sản phẩm.', 201);
        });
    }

    public function show(Product $product)
    {
        return ApiResponse::success($product->load(['owner:id,code,name', 'rentalRates', 'offerModes', 'transactions', 'availabilityHistory']));
    }

    public function update(ProductRequest $request, Product $product)
    {
        return DB::transaction(function () use ($request, $product) {
            $data = $request->validated();
            $rates = $data['rental_rates'] ?? null;
            $modes = $data['offer_modes'];
            unset($data['rental_rates'], $data['offer_modes']);
            $product->update($this->prepare($data));
            $product->syncOfferModes($modes);
            if ($rates !== null) {
                $this->syncRates($product, $rates);
            }

            return ApiResponse::success($product->fresh()->load(['rentalRates', 'offerModes']), 'Đã cập nhật sản phẩm.');
        });
    }

    public function approve(Product $product, MarketplaceNotificationService $notifications)
    {
        $product->update(['approval_status' => 'approved', 'is_published' => true, 'approved_at' => now(), 'approved_by' => user_id(), 'published_at' => $product->published_at ?? now(), 'rejection_reason' => null]);
        if ($product->owner_customer_id) {
            $notifications->send($product->owner_customer_id, 'product_approved', 'Sản phẩm đã được duyệt', 'Sản phẩm '.$product->code.' đã được hiển thị trên MBN.', '/account/products', ['product_id' => $product->id]);
        }

        return ApiResponse::success($product->fresh()->load('offerModes'), 'Đã duyệt sản phẩm.');
    }

    public function reject(ProductRejectRequest $request, Product $product, MarketplaceNotificationService $notifications)
    {
        $reason = $request->validated()['reason'];
        $product->update(['approval_status' => 'rejected', 'is_published' => false, 'rejection_reason' => $reason, 'approved_at' => null, 'approved_by' => user_id()]);
        if ($product->owner_customer_id) {
            $notifications->send($product->owner_customer_id, 'product_rejected', 'Sản phẩm cần chỉnh sửa', 'Sản phẩm '.$product->code.' bị từ chối: '.$reason, '/account/products', ['product_id' => $product->id]);
        }

        return ApiResponse::success($product->fresh()->load('offerModes'), 'Đã từ chối sản phẩm.');
    }

    public function destroy(Product $product)
    {
        if ($product->transactions()->exists()) {
            return ApiResponse::error('Sản phẩm đã phát sinh giao dịch nên không thể xóa.', null, 409);
        }
        $product->delete();

        return ApiResponse::success(message: 'Đã xóa sản phẩm.');
    }

    private function syncRates(Product $product, array $rates): void
    {
        $product->rentalRates()->delete();
        foreach (array_values($rates) as $i => $rate) {
            $product->rentalRates()->create($rate + ['sort_order' => $i]);
        }
    }

    private function prepare(array $data, bool $creating = false): array
    {
        $data['slug'] ??= Str::slug($data['name']);
        $data['updated_by'] = user_id();
        if ($creating) {
            $data['created_by'] = user_id();
        }

        return $data;
    }
}
