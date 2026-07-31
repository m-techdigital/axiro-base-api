<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\CustomerRequest;
use App\Http\Requests\Common\ListQueryRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Support\Query\AppliesListQuery;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    use AppliesListQuery;

    public function index(ListQueryRequest $request)
    {
        $query = $this->applyListFilters(
            Customer::with('wallet'),
            $request,
            ['name', 'username', 'phone', 'email', 'code'],
            ['status'],
            ['id', 'code', 'name', 'username', 'status', 'created_at'],
        );

        return ApiResponse::paginated($query->paginate($request->perPage()));
    }

    public function store(CustomerRequest $request)
    {
        $data = $request->validated();
        $data['code'] ??= 'CUS-'.strtoupper(Str::random(8));
        $customer = Customer::create($data);
        CustomerWallet::firstOrCreate(['customer_id' => $customer->id]);

        return ApiResponse::success(
            $customer->load('wallet'),
            'Đã tạo khách hàng.',
            201,
        );
    }

    public function show(Customer $customer)
    {
        return ApiResponse::success(
            $customer->load(['wallet', 'products', 'listings']),
        );
    }

    public function update(CustomerRequest $request, Customer $customer)
    {
        $customer->update($request->validated());

        return ApiResponse::success(
            $customer->fresh('wallet'),
            'Đã cập nhật khách hàng.',
        );
    }

    public function destroy(Customer $customer)
    {
        abort_if(
            $customer->purchases()->exists() || $customer->sales()->exists(),
            409,
            'Khách hàng đã có giao dịch.',
        );

        $customer->delete();

        return ApiResponse::success(message: 'Đã xóa khách hàng.');
    }
}
