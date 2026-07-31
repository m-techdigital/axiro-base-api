<?php
namespace App\Services\Marketplace;
use App\Models\{Contract,ListingRentalRate,MarketplaceDispute,ProductListing,Transaction,TransactionCheckpoint,TransactionEvent,TransactionPayment};
use App\Services\Wallet\WalletLedgerService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class TransactionLifecycleService {
    public function __construct(private MarketplaceNotificationService $notifications,private WalletLedgerService $wallets,private MarketplaceFeeCalculator $fees){}
    public function createFromListing(ProductListing $listing,int $buyerId,array $data):Transaction{
        return DB::transaction(function()use($listing,$buyerId,$data){
            $locked=ProductListing::with('rentalRates')->lockForUpdate()->findOrFail($listing->id);
            if($locked->status!=='published')throw ValidationException::withMessages(['listing'=>'Tin đăng hiện không còn khả dụng.']);
            if($locked->owner_customer_id===$buyerId)throw ValidationException::withMessages(['listing'=>'Bạn không thể giao dịch với tin đăng của chính mình.']);
            $isRental=$locked->listing_type==='rental';$mode=$isRental?'rental':($data['purchase_mode']??'full');
            if($mode==='installment'&&!$locked->allow_installment)throw ValidationException::withMessages(['purchase_mode'=>'Tin đăng này không hỗ trợ trả góp.']);
            [$value,$deposit,$rentalMeta]=$isRental?$this->resolveRentalPricing($locked,$data):[(string)$locked->sale_price,(string)($locked->deposit_amount??0),[]];
            $fee=$this->fees->calculate($isRental?'rental':'purchase',$value);
            $total=bcsub(bcadd(bcadd(bcadd($value,$deposit,2),$fee['buyer_fee_amount'],2),$fee['tax_amount'],2),'0.00',2);
            $initial=match($mode){'deposit'=>(string)max((float)$deposit,(float)($data['initial_payment_amount']??0)),'installment'=>(string)max((float)($locked->minimum_initial_payment??0),(float)($data['initial_payment_amount']??0)),default=>$isRental?(string)bcadd((string)($rentalMeta['first_due_amount']??$total),bcadd($fee['buyer_fee_amount'],$fee['tax_amount'],2),2):$total};
            if(bccomp($initial,$total,2)>0)throw ValidationException::withMessages(['initial_payment_amount'=>'Khoản thanh toán ban đầu không được vượt tổng tiền.']);
            $transaction=Transaction::create([
                'code'=>'TRX-'.strtoupper(Str::random(10)),'transaction_type'=>$isRental?'rental':'purchase','purchase_mode'=>$mode,
                'listing_id'=>$locked->id,'product_id'=>$locked->product_id,'buyer_customer_id'=>$buyerId,'seller_customer_id'=>$locked->owner_customer_id,
                'transaction_value'=>$value,'service_fee'=>$fee['service_fee'],'buyer_fee_amount'=>$fee['buyer_fee_amount'],'seller_fee_amount'=>$fee['seller_fee_amount'],'tax_amount'=>$fee['tax_amount'],'seller_net_amount'=>$fee['seller_net_amount'],'fee_policy_version'=>$fee['fee_policy_version'],'fee_snapshot'=>$fee['fee_snapshot'],'discount'=>0,'deposit_amount'=>$deposit,'initial_payment_amount'=>$initial,
                'installment_count'=>$mode==='installment'?($data['installment_count']??$locked->max_installment_count??2):null,
                'installment_interval_unit'=>$mode==='installment'?($data['installment_interval_unit']??$locked->installment_interval_unit??'week'):null,
                'installment_interval_count'=>$mode==='installment'?($data['installment_interval_count']??$locked->installment_interval_count??1):null,
                'rental_period_unit'=>$rentalMeta['period_unit']??null,'rental_period_count'=>$rentalMeta['period_count']??null,
                'rental_billing_mode'=>$rentalMeta['billing_mode']??null,'rental_billing_cycle_unit'=>$rentalMeta['billing_cycle_unit']??null,'rental_billing_cycle_count'=>$rentalMeta['billing_cycle_count']??null,
                'total_payable'=>$total,'paid_amount'=>0,'refunded_amount'=>0,'escrow_amount'=>0,'released_amount'=>0,'wallet_paid_amount'=>0,
                'transaction_date'=>now()->toDateString(),'due_date'=>$data['due_date']??null,'next_payment_due_at'=>now()->toDateString(),
                'rental_start_at'=>$rentalMeta['start_at']??null,'rental_end_at'=>$rentalMeta['end_at']??null,'status'=>'pending_payment','payment_method'=>$data['payment_method']??null,'note'=>$data['note']??null,
            ]);
            $this->createPaymentPlan($transaction,$rentalMeta);
            $this->event($transaction,'created','customer',$buyerId,'Đã tạo giao dịch','Tin đăng đã được giữ chỗ để chờ thanh toán.');
            $this->notifications->transaction($transaction->seller_customer_id,'transaction_created','Có giao dịch mới','Một khách hàng đã tạo giao dịch từ tin đăng '.$locked->code.'.',$transaction->id,$transaction->code);
            $this->notifications->transaction($transaction->buyer_customer_id,'transaction_created','Đã tạo giao dịch','Giao dịch '.$transaction->code.' đã được tạo và đang chờ thanh toán.',$transaction->id,$transaction->code);
            return $this->load($transaction);
        });
    }
    private function resolveRentalPricing(ProductListing $listing,array $data):array{
        $rate=null;if(!empty($data['rental_rate_id']))$rate=$listing->rentalRates->firstWhere('id',(int)$data['rental_rate_id']);
        if(!$rate)$rate=$listing->rentalRates->where('is_active',true)->firstWhere('is_default',true)??$listing->rentalRates->where('is_active',true)->first();
        $unit=$rate?->period_unit??($data['rental_period_unit']??$listing->rental_period_unit??$listing->rental_price_unit??'day');
        $count=(int)($rate?->period_count??$data['rental_period_count']??$data['rental_period']??$listing->minimum_rental_period??1);$count=max(1,$count);
        $price=(string)($rate?->price??bcmul((string)$listing->rental_price,(string)$count,2));
        $deposit=(string)($rate?->deposit_amount??$listing->deposit_amount??0);
        $start=Carbon::parse($data['rental_start_at']??now());$end=$this->addPeriod($start->copy(),$unit,$count);
        $billingMode=$data['rental_billing_mode']??$listing->rental_billing_mode??'upfront';
        $cycleUnit=$data['rental_billing_cycle_unit']??$listing->rental_billing_cycle_unit??$unit;
        $cycleCount=max(1,(int)($data['rental_billing_cycle_count']??$listing->rental_billing_cycle_count??1));
        $cycles=$billingMode==='periodic'?max(1,(int)ceil($this->unitValue($unit,$count)/max(1,$this->unitValue($cycleUnit,$cycleCount)))):1;
        $firstFee=$cycles>1?bcdiv($price,(string)$cycles,2):$price;
        return [$price,$deposit,['period_unit'=>$unit,'period_count'=>$count,'start_at'=>$start,'end_at'=>$end,'billing_mode'=>$billingMode,'billing_cycle_unit'=>$cycleUnit,'billing_cycle_count'=>$cycleCount,'cycle_count'=>$cycles,'first_due_amount'=>bcadd($firstFee,$deposit,2)]];
    }
    private function unitValue(string $unit,int $count):int{return match($unit){'hour'=>$count,'day'=>$count*24,'week'=>$count*168,'month'=>$count*720,default=>$count*24};}
    private function addPeriod(Carbon $date,string $unit,int $count):Carbon{return match($unit){'hour'=>$date->addHours($count),'week'=>$date->addWeeks($count),'month'=>$date->addMonthsNoOverflow($count),default=>$date->addDays($count)};}
    private function createPaymentPlan(Transaction $t,array $rentalMeta=[]):void{
        if($t->transaction_type==='rental'){$this->createRentalPlan($t,$rentalMeta);return;}
        if($t->purchase_mode==='installment'){
            $count=max(2,(int)$t->installment_count);$first=(string)$t->initial_payment_amount;
            TransactionPayment::create($this->paymentData($t,'installment','principal',1,null,$first,now(),false));
            $remaining=bcsub((string)$t->total_payable,$first,2);$per=bcdiv($remaining,(string)($count-1),2);$allocated='0.00';
            for($n=2;$n<=$count;$n++){$amount=$n===$count?bcsub($remaining,$allocated,2):$per;$allocated=bcadd($allocated,$amount,2);$due=$this->addPeriod(now(),$t->installment_interval_unit??'week',($n-1)*max(1,(int)$t->installment_interval_count));TransactionPayment::create($this->paymentData($t,'installment','principal',$n,null,$amount,$due,false));}
            $this->syncNextDue($t);return;
        }
        $type=$t->purchase_mode==='deposit'?'deposit':'full';TransactionPayment::create($this->paymentData($t,$type,'principal',null,null,(string)$t->initial_payment_amount,now(),false));
        if($t->purchase_mode==='deposit'){$remaining=bcsub((string)$t->total_payable,(string)$t->initial_payment_amount,2);if(bccomp($remaining,'0.00',2)>0)TransactionPayment::create($this->paymentData($t,'final','principal',null,null,$remaining,now()->addDays(7),false));}
        $this->syncNextDue($t);
    }
    private function createRentalPlan(Transaction $t,array $meta):void{
        if(bccomp((string)$t->deposit_amount,'0.00',2)>0)TransactionPayment::create($this->paymentData($t,'security_deposit','security_deposit',null,0,(string)$t->deposit_amount,now(),true,$t->rental_start_at?->toDateString(),$t->rental_end_at?->toDateString()));
        $cycles=max(1,(int)($meta['cycle_count']??1));$total=(string)$t->transaction_value;$per=bcdiv($total,(string)$cycles,2);$allocated='0.00';$start=Carbon::parse($t->rental_start_at??now());
        for($n=1;$n<=$cycles;$n++){$amount=$n===$cycles?bcsub($total,$allocated,2):$per;$allocated=bcadd($allocated,$amount,2);if($n===1)$amount=bcadd($amount,bcadd((string)$t->buyer_fee_amount,(string)$t->tax_amount,2),2);$periodStart=$n===1?$start->copy():$this->addPeriod($start->copy(),$t->rental_billing_cycle_unit??$t->rental_period_unit??'day',($n-1)*max(1,(int)($t->rental_billing_cycle_count??1)));$periodEnd=$this->addPeriod($periodStart->copy(),$t->rental_billing_cycle_unit??$t->rental_period_unit??'day',max(1,(int)($t->rental_billing_cycle_count??1)));$due=$t->rental_billing_mode==='upfront'?now():$periodStart->copy();TransactionPayment::create($this->paymentData($t,'rental_cycle','rental_fee',null,$n,$amount,$due,false,$periodStart->toDateString(),(($t->rental_end_at && $periodEnd->greaterThan($t->rental_end_at)) ? $t->rental_end_at : $periodEnd)->toDateString()));}
        $this->syncNextDue($t);
    }
    private function paymentData(Transaction $t,string $type,string $component,?int $installment,?int $cycle,string $amount,$due,bool $refundable=false,?string $periodStart=null,?string $periodEnd=null):array{return ['code'=>'PAY-'.strtoupper(Str::random(10)),'transaction_id'=>$t->id,'customer_id'=>$t->buyer_customer_id,'payment_type'=>$type,'component_type'=>$component,'installment_number'=>$installment,'cycle_number'=>$cycle,'amount'=>$amount,'refundable'=>$refundable,'status'=>'pending','settlement_status'=>'unsettled','period_start'=>$periodStart,'period_end'=>$periodEnd,'due_date'=>Carbon::parse($due)->toDateString()];}
    public function submitPayment(TransactionPayment $payment,int $customerId,array $data):TransactionPayment{
        abort_unless($payment->customer_id===$customerId,403);if(!in_array($payment->status,['pending','rejected','overdue'],true))throw ValidationException::withMessages(['payment'=>'Khoản thanh toán không thể gửi lại.']);
        if(($data['payment_method']??null)==='wallet')return DB::transaction(function()use($payment,$customerId,$data){$locked=TransactionPayment::lockForUpdate()->findOrFail($payment->id);$transaction=Transaction::lockForUpdate()->findOrFail($locked->transaction_id);$this->reserveListingForPayment($transaction);$walletEntry=$this->wallets->debitAvailable($customerId,(string)$locked->amount,'transaction_payment',['idempotency_key'=>'payment:'.$locked->id.':buyer-debit','transaction_id'=>$transaction->id,'transaction_payment_id'=>$locked->id,'payment_method'=>'wallet','reference_type'=>'transaction_payment','reference_id'=>$locked->id,'note'=>'Thanh toán '.$locked->code]);$this->wallets->creditHeld($transaction->seller_customer_id,(string)$locked->amount,'escrow_hold',['idempotency_key'=>'payment:'.$locked->id.':seller-hold','transaction_id'=>$transaction->id,'transaction_payment_id'=>$locked->id,'payment_method'=>'wallet','reference_type'=>'transaction_payment','reference_id'=>$locked->id,'note'=>'Tạm giữ tiền giao dịch '.$transaction->code]);$locked->update(['status'=>'confirmed','payment_method'=>'wallet','reference'=>$data['reference']??$walletEntry->code,'paid_at'=>now(),'confirmed_at'=>now(),'wallet_transaction_id'=>$walletEntry->id,'settlement_status'=>'held','settled_at'=>now(),'note'=>$data['note']??null]);$this->recalculatePaymentState($transaction);$this->event($transaction,'payment_confirmed','customer',$customerId,'Đã thanh toán bằng số dư ví','Khoản '.$locked->code.' đã được xác nhận tự động.',['payment_id'=>$locked->id]);return $locked->fresh();});
        $payment->update(['status'=>'submitted','payment_method'=>$data['payment_method'],'reference'=>$data['reference']??null,'paid_at'=>now(),'note'=>$data['note']??null]);$this->event($payment->transaction,'payment_submitted','customer',$customerId,'Đã gửi thông tin thanh toán','Khách hàng đã gửi thông tin thanh toán '.$payment->code.'.');return $payment->fresh();
    }
    public function confirmPayment(TransactionPayment $payment,int $adminId):TransactionPayment{return DB::transaction(function()use($payment,$adminId){$locked=TransactionPayment::lockForUpdate()->findOrFail($payment->id);if($locked->status==='confirmed')return $locked;if(!in_array($locked->status,['pending','submitted'],true))throw ValidationException::withMessages(['payment'=>'Khoản thanh toán không ở trạng thái có thể xác nhận.']);$transaction=Transaction::lockForUpdate()->findOrFail($locked->transaction_id);$this->reserveListingForPayment($transaction);$this->wallets->creditHeld($transaction->seller_customer_id,(string)$locked->amount,'escrow_hold',['idempotency_key'=>'payment:'.$locked->id.':seller-hold','transaction_id'=>$transaction->id,'transaction_payment_id'=>$locked->id,'payment_method'=>$locked->payment_method??'bank','reference_type'=>'transaction_payment','reference_id'=>$locked->id,'confirmed_by'=>$adminId,'note'=>'Đối soát thanh toán '.$locked->code]);$locked->update(['status'=>'confirmed','confirmed_at'=>now(),'confirmed_by'=>$adminId,'paid_at'=>$locked->paid_at??now(),'settlement_status'=>'held','settled_at'=>now()]);$this->recalculatePaymentState($transaction);$this->event($transaction,'payment_confirmed','user',$adminId,'Đã xác nhận thanh toán','Khoản '.$locked->code.' đã được xác nhận.',['payment_id'=>$locked->id]);foreach([$transaction->buyer_customer_id,$transaction->seller_customer_id] as $id)$this->notifications->transaction($id,'payment_confirmed','Thanh toán đã được xác nhận','Khoản '.$locked->code.' đã được đối soát.',$transaction->id,$transaction->code);return $locked->fresh(['transaction']);});}

    private function reserveListingForPayment(Transaction $transaction): void
    {
        if (! $transaction->listing_id) {
            return;
        }

        $listing = ProductListing::lockForUpdate()->findOrFail($transaction->listing_id);
        $conflictingTransactionExists = Transaction::query()
            ->where('listing_id', $listing->id)
            ->where('id', '!=', $transaction->id)
            ->where('status', '!=', 'cancelled')
            ->whereHas('payments', fn ($query) => $query->where('status', 'confirmed'))
            ->exists();

        if ($conflictingTransactionExists || ! in_array($listing->status, ['published', 'reserved'], true)) {
            throw ValidationException::withMessages([
                'listing' => 'Tin đăng đã được giữ chỗ bởi một giao dịch khác.',
            ]);
        }

        if ($listing->status === 'published') {
            $listing->update(['status' => 'reserved']);
        }
    }


    private function releaseListingAfterCancellation(Transaction $transaction): void
    {
        if (! $transaction->listing_id) {
            return;
        }

        $listing = ProductListing::lockForUpdate()->find($transaction->listing_id);
        if (! $listing || $listing->status !== 'reserved') {
            return;
        }

        $anotherPaidTransactionExists = Transaction::query()
            ->where('listing_id', $listing->id)
            ->where('id', '!=', $transaction->id)
            ->where('status', '!=', 'cancelled')
            ->whereHas('payments', fn ($query) => $query->where('status', 'confirmed'))
            ->exists();

        if (! $anotherPaidTransactionExists) {
            $listing->update(['status' => 'published']);
        }
    }

    private function recalculatePaymentState(Transaction $t):void{$paid=(string)$t->payments()->where('status','confirmed')->sum('amount');$walletPaid=(string)$t->payments()->where('status','confirmed')->where('payment_method','wallet')->sum('amount');$paymentStatus=bccomp($paid,(string)$t->total_payable,2)>=0?'paid':'partially_paid';$status=in_array($t->status,['pending_payment','partially_paid','paid','overdue'],true)?$paymentStatus:$t->status;$next=$t->payments()->whereIn('status',['pending','rejected'])->orderBy('due_date')->value('due_date');$t->update(['paid_amount'=>$paid,'wallet_paid_amount'=>$walletPaid,'escrow_amount'=>$paid,'status'=>$status,'next_payment_due_at'=>$next]);}
    private function syncNextDue(Transaction $t):void{$next=$t->payments()->where('status','pending')->orderBy('due_date')->value('due_date');$t->update(['next_payment_due_at'=>$next]);}

    private function startObligationsSatisfied(Transaction $t):bool{
        return !$t->payments()->whereIn('status',['pending','rejected','overdue'])->where(function($q){$q->whereNull('due_date')->orWhereDate('due_date','<=',today());})->exists();
    }
    public function allowedActions(Transaction $t,int $customerId):array{$buyer=$t->buyer_customer_id===$customerId;$seller=$t->seller_customer_id===$customerId;$a=[];if($seller&&in_array($t->status,['paid','partially_paid'],true)&&$this->startObligationsSatisfied($t))$a[]='seller_handover';if($buyer&&$t->status==='handover_pending')$a[]='buyer_receive';if($buyer&&$t->transaction_type==='rental'&&in_array($t->status,['active','overdue'],true))$a[]='renter_return';if($seller&&$t->transaction_type==='rental'&&$t->status==='return_pending')$a[]='lessor_receive_return';if($buyer&&bccomp((string)$t->paid_amount,(string)$t->total_payable,2)>=0&&(($t->transaction_type==='purchase'&&$t->status==='handed_over')||($t->transaction_type==='rental'&&$t->status==='returned')))$a[]='complete';if(($buyer||$seller)&&!in_array($t->status,['completed','cancelled'],true)&&!$t->disputes()->where('status','open')->exists())$a[]='open_dispute';return $a;}
    public function transition(Transaction $transaction,string $action,string $actorType,int $actorId):Transaction{return DB::transaction(function()use($transaction,$action,$actorType,$actorId){$t=Transaction::lockForUpdate()->findOrFail($transaction->id);if($actorType==='customer'&&!in_array($action,$this->allowedActions($t,$actorId),true))throw ValidationException::withMessages(['action'=>'Bạn không có quyền thực hiện hành động này ở trạng thái hiện tại.']);$next=$t->status;$checkpoint=null;$title='Đã cập nhật giao dịch';if($action==='seller_handover'){$next='handover_pending';$checkpoint='seller_handover';$title='Bên giao đã xác nhận bàn giao';}elseif($action==='buyer_receive'){$next=$t->transaction_type==='rental'?'active':'handed_over';$checkpoint='buyer_received';$title='Bên nhận đã xác nhận nhận tài khoản';}elseif($action==='renter_return'){$next='return_pending';$checkpoint='renter_returned';$title='Người thuê đã gửi yêu cầu hoàn trả';}elseif($action==='lessor_receive_return'){$next='returned';$checkpoint='lessor_received_return';$title='Người cho thuê đã xác nhận hoàn trả';}elseif($action==='complete'){$next='completed';$title='Giao dịch đã hoàn tất';}elseif($action==='cancel'){$next='cancelled';$title='Giao dịch đã hủy';}else throw ValidationException::withMessages(['action'=>'Hành động không hợp lệ.']);$updates=['status'=>$next];if(in_array($next,['handover_pending','handed_over','active'],true)&&!$t->handed_over_at)$updates['handed_over_at']=now();if($next==='returned')$updates['returned_at']=now();if($next==='completed')$updates['completed_at']=now();$t->update($updates);if($checkpoint)TransactionCheckpoint::updateOrCreate(['transaction_id'=>$t->id,'checkpoint'=>$checkpoint],['customer_id'=>$actorType==='customer'?$actorId:null,'actor_type'=>$actorType,'actor_id'=>$actorId,'confirmed_at'=>now()]);if($next==='completed'){$this->settleCompleted($t);$t->listing?->update(['status'=>'completed']);}if($next==='cancelled')$this->releaseListingAfterCancellation($t);$this->event($t,$action,$actorType,$actorId,$title,null,['checkpoint'=>$checkpoint]);$this->ensureContract($t);return $this->load($t);});}
    private function settleCompleted(Transaction $t):void{
        $payments=$t->payments()->where('status','confirmed')->where('settlement_status','held')->get();
        $gross='0.00';$refunded='0.00';
        foreach($payments as $p){
            if($t->transaction_type==='rental'&&$p->refundable){
                $ctx=['idempotency_key'=>'payment:'.$p->id.':deposit-refund','transaction_id'=>$t->id,'transaction_payment_id'=>$p->id,'reference_type'=>'transaction_payment','reference_id'=>$p->id];
                $this->wallets->transferHeldToAvailable($t->seller_customer_id,$t->buyer_customer_id,(string)$p->amount,'rental_deposit_refund',$ctx);
                $refunded=bcadd($refunded,(string)$p->amount,2);$p->update(['settlement_status'=>'refunded','released_at'=>now()]);
            }else{$gross=bcadd($gross,(string)$p->amount,2);}
        }
        $released='0.00';
        if(bccomp($gross,'0.00',2)>0){
            $net=(string)$t->seller_net_amount;
            [,,$platformFee]=$this->wallets->settleHeldWithFee($t->seller_customer_id,$gross,$net,['idempotency_key'=>'transaction:'.$t->id.':net-settlement','transaction_id'=>$t->id,'reference_type'=>'transaction','reference_id'=>$t->id,'note'=>'Quyết toán ròng giao dịch '.$t->code]);
            \App\Models\MarketplacePlatformLedgerEntry::firstOrCreate(['idempotency_key'=>'transaction:'.$t->id.':platform-fee'],['code'=>'PLF-'.strtoupper(\Illuminate\Support\Str::random(10)),'transaction_id'=>$t->id,'type'=>'marketplace_fee','amount'=>$platformFee,'metadata'=>['buyer_fee_amount'=>$t->buyer_fee_amount,'seller_fee_amount'=>$t->seller_fee_amount,'tax_amount'=>$t->tax_amount,'fee_policy_version'=>$t->fee_policy_version],'occurred_at'=>now()]);
            $t->payments()->where('status','confirmed')->where('settlement_status','held')->where('refundable',false)->update(['settlement_status'=>'released','released_at'=>now()]);
            $released=$net;
        }
        $t->update(['released_amount'=>bcadd((string)$t->released_amount,$released,2),'refunded_amount'=>bcadd((string)$t->refunded_amount,$refunded,2),'escrow_amount'=>'0.00']);
    }
    public function adminTransition(Transaction $transaction,string $action,int $adminId,?string $note=null):Transaction{return DB::transaction(function()use($transaction,$action,$adminId,$note){$t=Transaction::lockForUpdate()->findOrFail($transaction->id);$map=['force_handover'=>$t->transaction_type==='rental'?'active':'handed_over','force_return'=>'returned','complete'=>'completed','cancel'=>'cancelled','reopen'=>'pending_payment'];if(!isset($map[$action]))throw ValidationException::withMessages(['action'=>'Hành động quản trị không hợp lệ.']);$t->update(['status'=>$map[$action]]);if($map[$action]==='completed')$this->settleCompleted($t);if($map[$action]==='cancelled'){$this->refundHeldPayments($t,'admin_cancel');$this->releaseListingAfterCancellation($t);}$this->event($t,'admin_'.$action,'user',$adminId,'Quản trị viên cập nhật giao dịch',$note);$this->ensureContract($t);return $this->load($t);});}

    private function refundHeldPayments(Transaction $t,string $reason):void{
        $payments=$t->payments()->where('status','confirmed')->where('settlement_status','held')->get();$refunded='0.00';
        foreach($payments as $p){$ctx=['idempotency_key'=>'payment:'.$p->id.':refund:'.$reason,'transaction_id'=>$t->id,'transaction_payment_id'=>$p->id,'reference_type'=>'transaction_payment','reference_id'=>$p->id,'note'=>'Hoàn tiền giao dịch '.$t->code];$this->wallets->transferHeldToAvailable($t->seller_customer_id,$t->buyer_customer_id,(string)$p->amount,'transaction_refund',$ctx);$p->update(['settlement_status'=>'refunded','released_at'=>now()]);$refunded=bcadd($refunded,(string)$p->amount,2);}
        if(bccomp($refunded,'0.00',2)>0)$t->update(['refunded_amount'=>bcadd((string)$t->refunded_amount,$refunded,2),'escrow_amount'=>'0.00']);
    }
    private function ensureContract(Transaction $t):void{if($t->contract()->exists()||!in_array($t->status,['paid','active','handed_over','returned','completed'],true))return;Contract::create(['code'=>'CTR-'.strtoupper(Str::random(10)),'transaction_id'=>$t->id,'contract_type'=>$t->transaction_type==='purchase'?'sale':'rental','title'=>'Thỏa thuận '.$t->code,'contract_value'=>$t->total_payable,'deposit_amount'=>$t->deposit_amount,'signed_at'=>now()->toDateString(),'start_date'=>$t->rental_start_at?->toDateString()??now()->toDateString(),'end_date'=>$t->rental_end_at?->toDateString(),'status'=>'active']);}
    public function openDispute(Transaction $t,int $customerId,array $data):MarketplaceDispute{abort_unless(in_array($customerId,[$t->buyer_customer_id,$t->seller_customer_id],true),403);$d=MarketplaceDispute::create(['code'=>'DSP-'.strtoupper(Str::random(10)),'transaction_id'=>$t->id,'opened_by_customer_id'=>$customerId,'reason'=>$data['reason'],'status'=>'open','description'=>$data['description'],'evidence'=>$data['evidence']??[]]);$t->update(['status'=>'disputed']);$this->event($t,'dispute_opened','customer',$customerId,'Đã mở yêu cầu tranh chấp',$data['description']);return $d->fresh(['transaction','openedBy:id,code,name']);}
    public function resolveDispute(MarketplaceDispute $d,int $adminId,array $data):MarketplaceDispute{return DB::transaction(function()use($d,$adminId,$data){$d->update(['status'=>$data['status'],'resolution'=>$data['resolution'],'resolved_at'=>now(),'resolved_by'=>$adminId]);$next=$data['transaction_status']??($data['status']==='resolved'?'completed':'cancelled');$d->transaction->update(['status'=>$next]);if($next==='completed')$this->settleCompleted($d->transaction);elseif($next==='cancelled'){$this->refundHeldPayments($d->transaction,'dispute_cancel');$this->releaseListingAfterCancellation($d->transaction);}$this->event($d->transaction,'dispute_resolved','user',$adminId,'Đã xử lý tranh chấp',$data['resolution']);return $d->fresh(['transaction','openedBy:id,code,name']);});}
    public function event(Transaction $t,string $type,?string $actorType,?int $actorId,string $title,?string $description=null,array $metadata=[]):TransactionEvent{return TransactionEvent::create(['transaction_id'=>$t->id,'event_type'=>$type,'actor_type'=>$actorType,'actor_id'=>$actorId,'title'=>$title,'description'=>$description,'metadata'=>$metadata]);}
    public function load(Transaction $t):Transaction{return $t->fresh(['product','listing.rentalRates','buyer:id,code,name,avatar_url','seller:id,code,name,avatar_url','contract','payments','events','disputes','checkpoints','documents','assetSnapshots']);}
}
