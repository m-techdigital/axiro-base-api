<?php
namespace App\Services\Marketplace;
use App\Models\MarketplaceRiskFlag;
use Illuminate\Support\Str;
final class MarketplaceRiskService {
 public function flag(string $subjectType,int $subjectId,string $ruleCode,string $level,string $reason,array $evidence=[]): MarketplaceRiskFlag {
  return MarketplaceRiskFlag::firstOrCreate(['subject_type'=>$subjectType,'subject_id'=>$subjectId,'rule_code'=>$ruleCode,'status'=>'open'],['code'=>'RSK-'.strtoupper(Str::random(10)),'level'=>$level,'reason'=>$reason,'evidence'=>$evidence]);
 }
 public function evaluateWithdrawal(int $withdrawalId,string $amount,int $customerId): void {
  $threshold=(string)config('marketplace.risk.withdrawal_review_threshold','10000000');
  if(bccomp($amount,$threshold,2)>=0)$this->flag('withdrawal_request',$withdrawalId,'large_withdrawal','high','Yêu cầu rút tiền vượt ngưỡng cần kiểm tra thủ công.',['amount'=>$amount,'customer_id'=>$customerId,'threshold'=>$threshold]);
 }
}
