<?php
namespace App\Http\Controllers;
use App\Models\MarketplacePaymentSetting;
use App\Services\Payments\MarketplaceQrService;
use Illuminate\Http\Request;
class MarketplacePaymentSettingController extends Controller {
    public function show(MarketplaceQrService $qr){return success_response($qr->setting());}
    public function update(Request $r,MarketplaceQrService $qr){
        $d=$r->validate(['bank_id'=>'required|string|max:32','bank_name'=>'required|string|max:120','account_no'=>'required|string|max:80','account_name'=>'required|string|max:180','qr_template'=>'required|in:compact,compact2,qr_only,print','transfer_prefix'=>'required|string|max:32']);
        MarketplacePaymentSetting::query()->update(['is_active'=>false]);
        MarketplacePaymentSetting::create([...$d,'is_active'=>true]);
        return success_response($qr->setting(),'Đã cập nhật thông tin nhận thanh toán.');
    }
    public function preview(Request $r,MarketplaceQrService $qr){$d=$r->validate(['amount'=>'required|numeric|min:1','reference'=>'required|string|max:100']);return success_response($qr->make($d['reference'],$d['amount']));}
}
