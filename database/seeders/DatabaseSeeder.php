<?php

namespace Database\Seeders;

use App\Models\MarketplaceFeePolicy;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['username' => env('ADMIN_USERNAME', 'admin')],
            [
                'name' => env('ADMIN_NAME', 'Administrator'),
                'email' => env('ADMIN_EMAIL', 'admin@example.com'),
                'password' => env('ADMIN_PASSWORD', 'change-me'),
            ],
        );


        \App\Models\MarketplacePaymentSetting::query()->firstOrCreate(
            ['is_active' => true],
            [
                'bank_id' => env('MARKETPLACE_BANK_ID', 'MB'),
                'bank_name' => env('MARKETPLACE_BANK_NAME', 'MB BANK'),
                'account_no' => env('MARKETPLACE_BANK_ACCOUNT_NO', '0123456789'),
                'account_name' => env('MARKETPLACE_BANK_ACCOUNT_NAME', 'NGUYEN VAN A'),
                'qr_template' => env('MARKETPLACE_BANK_QR_TEMPLATE', 'compact2'),
                'transfer_prefix' => 'MBN',
            ],
        );

        MarketplaceFeePolicy::query()->updateOrCreate(
            ['code' => 'DEMO-ZERO-FEE'],
            [
                'name' => 'Chính sách phí demo mặc định',
                'transaction_type' => null,
                'buyer_fee_rate' => 0,
                'buyer_fixed_fee' => 0,
                'seller_fee_rate' => 0,
                'seller_fixed_fee' => 0,
                'tax_rate' => 0,
                'priority' => 100,
                'is_active' => true,
                'effective_from' => now()->subYear(),
                'effective_to' => null,
                'conditions' => ['scope' => 'demo'],
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        );

        $this->call(MarketplaceDemoSeeder::class);
        $this->call(MarketplaceDocumentSeeder::class);
    }
}
