<?php
namespace App\Http\Controllers;
use App\Models\ProductListing;
use App\Services\Marketplace\MarketplaceNotificationService;
use Illuminate\Http\Request;
class ListingController extends Controller {
 public function index(Request $r){$q=ProductListing::with(['product','owner:id,code,name','approver:id,name','rentalRates'])->when($r->keyword,fn($q,$v)=>$q->where(fn($x)=>$x->where('code','like',"%$v%")->orWhere('title','like',"%$v%")))->when($r->status,fn($q,$v)=>$q->where('status',$v))->when($r->listing_type,fn($q,$v)=>$q->where('listing_type',$v))->latest();return success_response($q->paginate(min(100,max(1,$r->integer('per_page',20)))));}
 public function show(ProductListing $listing){return success_response($listing->load(['product','owner:id,code,name','transactions','rentalRates']));}
 public function approve(ProductListing $listing,MarketplaceNotificationService $notifications){$listing->update(['status'=>'published','approved_at'=>now(),'approved_by'=>user_id(),'published_at'=>$listing->published_at??now(),'rejection_reason'=>null]);$notifications->send($listing->owner_customer_id,'listing_approved','Tin đăng đã được duyệt','Tin '.$listing->code.' đã được hiển thị trên MBN.', '/account/listings',['listing_id'=>$listing->id]);return success_response($listing->fresh(['product','owner']));}
 public function reject(Request $r,ProductListing $listing,MarketplaceNotificationService $notifications){$d=$r->validate(['reason'=>'required|string|max:2000']);$listing->update(['status'=>'rejected','rejection_reason'=>$d['reason'],'approved_at'=>null,'approved_by'=>user_id()]);$notifications->send($listing->owner_customer_id,'listing_rejected','Tin đăng cần chỉnh sửa','Tin '.$listing->code.' bị từ chối: '.$d['reason'], '/account/listings',['listing_id'=>$listing->id]);return success_response($listing->fresh(['product','owner']));}
}
