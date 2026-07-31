<?php

namespace App\Services\Payments;

use App\Models\MarketplacePaymentSetting;

class MarketplaceQrService
{
    public function setting(): array
    {
        $row = MarketplacePaymentSetting::query()
            ->where('is_active', true)
            ->latest('id')
            ->first();

        if ($row) {
            return [
                'id' => $row->bank_id,
                'name' => $row->bank_name,
                'account_no' => $row->account_no,
                'account_name' => $row->account_name,
                'qr_template' => $row->qr_template,
                'transfer_prefix' => $row->transfer_prefix,
            ];
        }

        $bank = config('marketplace.bank', []);
        if (! is_array($bank)) {
            $bank = [];
        }

        return array_replace([
            'id' => 'MB',
            'name' => 'MB BANK',
            'account_no' => '0123456789',
            'account_name' => 'NGUYEN VAN A',
            'qr_template' => 'compact2',
            'transfer_prefix' => 'MBN',
        ], $bank);
    }

    public function make(string $reference, string|int|float $amount): array
    {
        $bank = $this->setting();
        $content = trim(($bank['transfer_prefix'] ?? 'MBN').' '.$reference);
        $url = 'https://img.vietqr.io/image/'
            .rawurlencode((string) $bank['id']).'-'
            .rawurlencode((string) $bank['account_no']).'-'
            .rawurlencode((string) ($bank['qr_template'] ?? 'compact2'))
            .'.png?amount='.(int) $amount
            .'&addInfo='.rawurlencode($content)
            .'&accountName='.rawurlencode((string) $bank['account_name']);

        return [
            'bank' => $bank,
            'qr_url' => $url,
            'transfer_content' => $content,
            'amount' => (string) $amount,
        ];
    }
}
