<?php
namespace App\Http\Controllers;
use App\Models\MarketplaceNotification;
use Illuminate\Http\Request;
class CustomerNotificationController extends Controller {
    public function index(Request $request) {
        $query=MarketplaceNotification::where('customer_id',auth('customer_api')->id())
            ->when($request->boolean('unread'),fn($q)=>$q->whereNull('read_at'))->latest();
        $page=$query->paginate(min(100,max(1,$request->integer('per_page',20))));
        return success_response(['notifications'=>$page,'unread_count'=>MarketplaceNotification::where('customer_id',auth('customer_api')->id())->whereNull('read_at')->count()]);
    }
    public function read(MarketplaceNotification $notification) {
        abort_unless($notification->customer_id===auth('customer_api')->id(),403);
        $notification->update(['read_at'=>$notification->read_at??now()]);
        return success_response($notification);
    }
    public function readAll() {
        MarketplaceNotification::where('customer_id',auth('customer_api')->id())->whereNull('read_at')->update(['read_at'=>now()]);
        return success_response();
    }
}
