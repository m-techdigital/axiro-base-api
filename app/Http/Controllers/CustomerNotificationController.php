<?php

namespace App\Http\Controllers;

use App\Http\Requests\Common\ListQueryRequest;
use App\Models\MarketplaceNotification;
use App\Support\Query\AppliesListQuery;

class CustomerNotificationController extends Controller
{
    use AppliesListQuery;

    public function index(ListQueryRequest $request)
    {
        $customerId = auth('customer_api')->id();
        $query = MarketplaceNotification::query()->where('customer_id', $customerId);

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        $query = $this->applyListFilters(
            $query,
            $request,
            ['title', 'message'],
            ['type'],
            ['id', 'created_at', 'read_at'],
            'id',
        );

        $page = $query->paginate($request->perPage());

        return success_response([
            'notifications' => $page,
            'unread_count' => MarketplaceNotification::where('customer_id', $customerId)->whereNull('read_at')->count(),
        ]);
    }

    public function read(MarketplaceNotification $notification)
    {
        abort_unless($notification->customer_id === auth('customer_api')->id(), 403);
        $notification->update(['read_at' => $notification->read_at ?? now()]);

        return success_response($notification);
    }

    public function readAll()
    {
        MarketplaceNotification::where('customer_id', auth('customer_api')->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return success_response();
    }
}
