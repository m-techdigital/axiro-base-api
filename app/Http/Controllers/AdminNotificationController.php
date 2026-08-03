<?php

namespace App\Http\Controllers;

use App\Http\Requests\Common\ListQueryRequest;
use App\Http\Responses\ApiResponse;
use App\Models\MarketplaceNotification;
use App\Support\Marketplace\TransactionLifecycleCatalog;
use Illuminate\Database\Eloquent\Builder;

class AdminNotificationController extends Controller
{
    public function index(ListQueryRequest $request)
    {
        $query = MarketplaceNotification::query()->with([
            'customer:id,code,name',
            'transaction:id,code,status',
        ]);

        if ($keyword = $request->keyword()) {
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder->where('title', 'like', "%{$keyword}%")
                    ->orWhere('message', 'like', "%{$keyword}%")
                    ->orWhere('transaction_code', 'like', "%{$keyword}%")
                    ->orWhereHas('customer', fn (Builder $customer): Builder => $customer
                        ->where('name', 'like', "%{$keyword}%")
                        ->orWhere('code', 'like', "%{$keyword}%"));
            });
        }

        foreach (['type', 'transaction_id', 'customer_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->validated($field));
            }
        }

        if ($request->validated('read_status') === 'unread' || $request->boolean('unread')) {
            $query->whereNull('read_at');
        } elseif ($request->validated('read_status') === 'read') {
            $query->whereNotNull('read_at');
        }

        $page = $query->latest('id')->paginate($request->perPage());

        return ApiResponse::paginated(
            $page,
            null,
            'Thành công',
            ['unread_count' => MarketplaceNotification::query()->whereNull('read_at')->count()],
        );
    }

    public function show(
        MarketplaceNotification $notification,
        TransactionLifecycleCatalog $catalog,
    ) {
        $notification->load([
            'customer:id,code,name,email,phone,status',
            'transaction' => fn ($query) => $query->with([
                'product:id,code,name,product_type,availability_status',
                'buyer:id,code,name,email,phone',
                'seller:id,code,name,email,phone',
                'events' => fn ($events) => $events->latest('id')->limit(100),
                'disputes' => fn ($disputes) => $disputes->latest('id')->limit(20),
            ]),
        ]);

        $actionContext = null;
        if ($notification->transaction) {
            $lifecycle = $catalog->describe($notification->transaction, 'admin');
            $actionContext = [
                'deep_link' => '/transactions/'.$notification->transaction->id,
                'next_action' => $lifecycle['next_action'],
                'blocking_reasons' => array_slice($lifecycle['blocking_reasons'], 0, 3),
                'transaction_status' => $lifecycle['status'],
            ];
        } elseif ($notification->customer_id) {
            $actionContext = [
                'deep_link' => '/customers/'.$notification->customer_id.'/edit',
                'next_action' => null,
                'blocking_reasons' => [],
                'transaction_status' => null,
            ];
        }

        $notification->setAttribute('action_context', $actionContext);

        return ApiResponse::success($notification);
    }

    public function read(MarketplaceNotification $notification)
    {
        $notification->update(['read_at' => $notification->read_at ?? now()]);

        return ApiResponse::success($notification->fresh(), 'Đã đánh dấu thông báo đã đọc.');
    }

    public function readAll()
    {
        MarketplaceNotification::query()->whereNull('read_at')->update(['read_at' => now()]);

        return ApiResponse::success(message: 'Đã đánh dấu toàn bộ thông báo đã đọc.');
    }
}
