<?php

namespace Database\Seeders;

use App\Models\EscrowFeeRule;
use App\Models\MarketplaceFeePolicy;
use App\Models\MarketplacePaymentSetting;
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

        $this->call(OfferModeSeeder::class);

        EscrowFeeRule::query()->updateOrCreate(
            ['code' => 'ESCROW-DEFAULT'],
            [
                'name' => 'Phí box giao dịch trung gian mặc định',
                'minimum_money_amount' => 0,
                'maximum_money_amount' => null,
                'base_fee' => 50000,
                'percentage_rate' => 10,
                'minimum_fee' => 50000,
                'maximum_fee' => null,
                'priority' => 100,
                'version' => 1,
                'is_active' => true,
                'effective_from' => now()->subYear(),
                'effective_to' => null,
                'conditions' => ['scope' => 'escrow_box'],
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        );

        $seedDemoData = filter_var(
            env('SEED_MARKETPLACE_DEMO', app()->environment(['local', 'testing'])),
            FILTER_VALIDATE_BOOL,
        );

        if (! $seedDemoData) {
            return;
        }

        MarketplacePaymentSetting::query()->firstOrCreate(
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
