<?php
namespace App\Console\Commands;
use App\Models\{ProductListing,SavedSearch};
use App\Services\Marketplace\MarketplaceNotificationService;
use Illuminate\Console\Command;
class ScanSavedSearchesCommand extends Command {
 protected $signature='marketplace:scan-saved-searches';
 protected $description='Notify customers when new listings match saved searches';
 public function handle(MarketplaceNotificationService $notifications): int {
  SavedSearch::where('notify',true)->chunkById(100,function($searches)use($notifications){foreach($searches as $search){$filters=$search->filters??[];$query=ProductListing::with('product')->where('status','published')->when($search->last_notified_at,fn($q,$v)=>$q->where('published_at','>',$v));if(!empty($filters['product_type']))$query->whereHas('product',fn($q)=>$q->where('product_type',$filters['product_type']));if(!empty($filters['keyword'])){$keyword=$filters['keyword'];$query->where(fn($q)=>$q->where('title','like',"%{$keyword}%")->orWhere('description','like',"%{$keyword}%"));}$matches=$query->latest('published_at')->limit(5)->get();if($matches->isNotEmpty())$notifications->send($search->customer_id,'listing_saved_search_match','Có tin đăng phù hợp',"Có {$matches->count()} tin đăng mới phù hợp với bộ lọc {$search->name}.",'/account/trust',['saved_search_id'=>$search->id,'listing_ids'=>$matches->pluck('id')->all()]);$search->update(['last_notified_at'=>now()]);}});return self::SUCCESS;
 }
}
