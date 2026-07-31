<?php

namespace App\Http\Controllers;

use App\Http\Requests\Common\ListQueryRequest;
use App\Http\Requests\ProductRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Product;
use App\Support\Query\AppliesListQuery;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    use AppliesListQuery;

    public function index(ListQueryRequest $request)
    {
        $query = $this->applyListFilters(
            Product::query(),
            $request,
            ['code', 'name'],
            ['status'],
            ['id', 'code', 'name', 'price', 'status', 'created_at'],
        );

        return ApiResponse::paginated($query->paginate($request->perPage()));
    }

    public function store(ProductRequest $request)
    {
        $data = $this->prepare($request->validated(), true);

        return ApiResponse::success(Product::create($data), 'Đã tạo sản phẩm.', 201);
    }

    public function show(Product $product)
    {
        return ApiResponse::success($product);
    }

    public function update(ProductRequest $request, Product $product)
    {
        $product->update($this->prepare($request->validated()));

        return ApiResponse::success($product->fresh(), 'Đã cập nhật sản phẩm.');
    }

    public function destroy(Product $product)
    {
        if ($product->transactions()->exists()) {
            return ApiResponse::error(
                'Sản phẩm đã phát sinh giao dịch nên không thể xóa.',
                null,
                409,
            );
        }

        $product->delete();

        return ApiResponse::success(message: 'Đã xóa sản phẩm.');
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
