<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\ResolveDisputeRequest;
use App\Http\Requests\Common\ListQueryRequest;
use App\Http\Responses\ApiResponse;
use App\Models\MarketplaceDispute;
use App\Services\Marketplace\TransactionLifecycleService;
use App\Support\Query\AppliesListQuery;

class DisputeController extends Controller
{
    use AppliesListQuery;

    public function index(ListQueryRequest $request)
    {
        $query = $this->applyListFilters(
            MarketplaceDispute::with(['transaction.product', 'openedBy:id,code,name']),
            $request,
            ['code', 'reason', 'description'],
            ['status', 'transaction_id'],
            ['id', 'code', 'status', 'created_at', 'updated_at'],
        );

        return ApiResponse::paginated($query->paginate($request->perPage()));
    }

    public function show(MarketplaceDispute $dispute)
    {
        return ApiResponse::success(
            $dispute->load([
                'transaction.product',
                'transaction.payments',
                'transaction.events',
                'openedBy:id,code,name',
            ]),
        );
    }

    public function resolve(
        ResolveDisputeRequest $request,
        MarketplaceDispute $dispute,
        TransactionLifecycleService $service,
    ) {
        return ApiResponse::success(
            $service->resolveDispute($dispute, user_id(), $request->validated()),
        );
    }
}
