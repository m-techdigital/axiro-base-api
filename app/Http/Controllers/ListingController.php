<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\ListingRejectRequest;
use App\Http\Requests\Common\ListQueryRequest;
use App\Http\Responses\ApiResponse;
use App\Models\ProductListing;
use App\Services\Marketplace\MarketplaceNotificationService;
use App\Support\Query\AppliesListQuery;

class ListingController extends Controller
{
    use AppliesListQuery;

    public function index(ListQueryRequest $request)
    {
        $query = $this->applyListFilters(
            ProductListing::with([
                'product',
                'owner:id,code,name',
                'approver:id,name',
                'rentalRates',
            ]),
            $request,
            ['code', 'title'],
            ['status', 'listing_type'],
            ['id', 'code', 'title', 'status', 'listing_type', 'created_at'],
        );

        return ApiResponse::paginated($query->paginate($request->perPage()));
    }

    public function show(ProductListing $listing)
    {
        return ApiResponse::success(
            $listing->load([
                'product',
                'owner:id,code,name',
                'transactions',
                'rentalRates',
            ]),
        );
    }

    public function approve(
        ProductListing $listing,
        MarketplaceNotificationService $notifications,
    ) {
        $listing->update([
            'status' => 'published',
            'approved_at' => now(),
            'approved_by' => user_id(),
            'published_at' => $listing->published_at ?? now(),
            'rejection_reason' => null,
        ]);

        $notifications->send(
            $listing->owner_customer_id,
            'listing_approved',
            'Tin đăng đã được duyệt',
            'Tin '.$listing->code.' đã được hiển thị trên MBN.',
            '/account/listings',
            ['listing_id' => $listing->id],
        );

        return ApiResponse::success(
            $listing->fresh(['product', 'owner']),
            'Đã duyệt tin đăng.',
        );
    }

    public function reject(
        ListingRejectRequest $request,
        ProductListing $listing,
        MarketplaceNotificationService $notifications,
    ) {
        $data = $request->validated();
        $listing->update([
            'status' => 'rejected',
            'rejection_reason' => $data['reason'],
            'approved_at' => null,
            'approved_by' => user_id(),
        ]);

        $notifications->send(
            $listing->owner_customer_id,
            'listing_rejected',
            'Tin đăng cần chỉnh sửa',
            'Tin '.$listing->code.' bị từ chối: '.$data['reason'],
            '/account/listings',
            ['listing_id' => $listing->id],
        );

        return ApiResponse::success(
            $listing->fresh(['product', 'owner']),
            'Đã từ chối tin đăng.',
        );
    }
}
