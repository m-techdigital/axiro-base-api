<?php

namespace App\Http\Controllers;

use App\Http\Requests\MarketplacePaymentSettingRequest;
use App\Models\MarketplacePaymentSetting;
use App\Services\Payments\MarketplaceQrService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketplacePaymentSettingController extends Controller
{
    public function show(MarketplaceQrService $qr)
    {
        return success_response($qr->setting());
    }

    public function history()
    {
        $rows = MarketplacePaymentSetting::query()
            ->latest('id')
            ->limit(20)
            ->get();

        return success_response($rows);
    }

    public function update(MarketplacePaymentSettingRequest $request, MarketplaceQrService $qr)
    {
        DB::transaction(function () use ($request): void {
            MarketplacePaymentSetting::query()->update(['is_active' => false]);
            MarketplacePaymentSetting::query()->create([
                ...$request->validated(),
                'is_active' => true,
            ]);
        });

        return success_response($qr->setting(), 'Đã cập nhật thông tin nhận thanh toán.');
    }

    public function activate(MarketplacePaymentSetting $paymentSetting, MarketplaceQrService $qr)
    {
        DB::transaction(function () use ($paymentSetting): void {
            MarketplacePaymentSetting::query()->update(['is_active' => false]);
            $paymentSetting->forceFill(['is_active' => true])->save();
        });

        return success_response($qr->setting(), 'Đã khôi phục cấu hình nhận thanh toán.');
    }

    public function preview(Request $request, MarketplaceQrService $qr)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'reference' => ['required', 'string', 'max:100'],
        ]);

        return success_response($qr->make($data['reference'], $data['amount']));
    }
}
