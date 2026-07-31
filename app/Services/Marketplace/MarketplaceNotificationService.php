<?php
namespace App\Services\Marketplace;
use App\Models\{MarketplaceNotification,NotificationPreference};
class MarketplaceNotificationService {
    public function send(int $customerId, string $type, string $title, string $message, ?string $actionUrl = null, array $data = []): ?MarketplaceNotification {
        $category=$this->category($type);
        $preference=NotificationPreference::where('customer_id',$customerId)->where('category',$category)->first();
        if($preference && !$preference->in_app && $category!=='security')return null;
        return MarketplaceNotification::create([
            'customer_id'=>$customerId,
            'type'=>$type,
            'title'=>$title,
            'message'=>$message,
            'action_url'=>$actionUrl,
            'data'=>$data,
        ]);
    }
    public function transaction(int $customerId, string $type, string $title, string $message, int $transactionId, string $transactionCode): ?MarketplaceNotification {
        return $this->send($customerId,$type,$title,$message,"/account/purchases/{$transactionId}",['transaction_id'=>$transactionId,'transaction_code'=>$transactionCode]);
    }
    private function category(string $type): string {
        if(str_contains($type,'payment')||str_contains($type,'deposit')||str_contains($type,'withdrawal'))return 'payment';
        if(str_contains($type,'handover')||str_contains($type,'return'))return 'handover';
        if(str_contains($type,'rental')||str_contains($type,'due'))return 'rental_due';
        if(str_contains($type,'document')||str_contains($type,'contract'))return 'document';
        if(str_contains($type,'case')||str_contains($type,'dispute'))return 'case';
        if(str_contains($type,'listing'))return 'listing';
        if(str_contains($type,'security')||str_contains($type,'login')||str_contains($type,'password'))return 'security';
        return 'transaction';
    }
}
