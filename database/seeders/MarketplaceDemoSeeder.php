<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerPayoutAccount;
use App\Models\CustomerVerification;
use App\Models\CustomerWallet;
use App\Models\MarketplaceDispute;
use App\Models\MarketplaceNotification;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionCheckpoint;
use App\Models\TransactionEvent;
use App\Models\TransactionPayment;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Payouts\WithdrawalService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketplaceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(OfferModeSeeder::class);

        DB::transaction(function (): void {
            $admin = User::query()->firstOrFail();
            $customers = $this->customers();
            $this->wallets($customers, $admin);
            $this->payouts($customers, $admin);
            $products = $this->products($customers, $admin);
            $transactions = $this->transactions($products, $customers, $admin);
            $this->payments($transactions, $customers, $admin);
            $this->eventsAndCheckpoints($transactions, $customers);
            $this->disputes($transactions, $customers, $admin);
            $this->notifications($transactions, $customers);
        });
    }

    private function customers(): array
    {
        $rows = [
            'buyer' => ['code' => 'CUS-DEMO', 'username' => 'customer', 'name' => 'Khách mua mẫu', 'email' => 'customer@example.com', 'phone' => '0900000000'],
            'seller' => ['code' => 'CUS-SELLER', 'username' => 'seller', 'name' => 'Người bán mẫu', 'email' => 'seller@example.com', 'phone' => '0911111111'],
            'renter' => ['code' => 'CUS-RENTER', 'username' => 'renter', 'name' => 'Người thuê mẫu', 'email' => 'renter@example.com', 'phone' => '0922222222'],
            'lessor' => ['code' => 'CUS-LESSOR', 'username' => 'lessor', 'name' => 'Người cho thuê mẫu', 'email' => 'lessor@example.com', 'phone' => '0933333333'],
            'dispute' => ['code' => 'CUS-DISPUTE', 'username' => 'dispute', 'name' => 'Khách khiếu nại mẫu', 'email' => 'dispute@example.com', 'phone' => '0944444444'],
        ];

        foreach ($rows as $key => $row) {
            $rows[$key] = Customer::query()->updateOrCreate(
                ['username' => $row['username']],
                [...$row, 'password' => env('DEMO_CUSTOMER_PASSWORD', 'change-me'), 'status' => 'active'],
            );
        }

        return $rows;
    }

    private function wallets(array $customers, User $admin): void
    {
        foreach (['buyer' => 2_450_000, 'seller' => 4_820_000, 'renter' => 1_300_000, 'lessor' => 3_100_000, 'dispute' => 880_000] as $key => $balance) {
            CustomerWallet::query()->updateOrCreate(
                ['customer_id' => $customers[$key]->id],
                ['available_balance' => $balance, 'held_balance' => $key === 'dispute' ? 500_000 : 0],
            );
        }

        $rows = [
            ['code' => 'WAL-DEMO-001', 'customer' => 'buyer', 'type' => 'deposit', 'status' => 'confirmed', 'amount' => 1_000_000, 'available_before' => 1_450_000, 'available_after' => 2_450_000],
            ['code' => 'NAP-DEMO-PENDING', 'customer' => 'buyer', 'type' => 'deposit_request', 'status' => 'submitted', 'amount' => 500_000, 'available_before' => 2_450_000, 'available_after' => 2_450_000],
            ['code' => 'NAP-DEMO-REJECTED', 'customer' => 'renter', 'type' => 'deposit_request', 'status' => 'rejected', 'amount' => 200_000, 'available_before' => 1_300_000, 'available_after' => 1_300_000],
        ];

        foreach ($rows as $row) {
            $customer = $customers[$row['customer']];
            unset($row['customer']);
            WalletTransaction::query()->updateOrCreate(['code' => $row['code']], [
                ...$row,
                'customer_id' => $customer->id,
                'direction' => 'credit',
                'balance_bucket' => 'available',
                'held_before' => 0,
                'held_after' => 0,
                'balance_after' => $row['available_after'],
                'payment_method' => 'bank',
                'occurred_at' => now(),
                'submitted_at' => $row['status'] === 'submitted' ? now() : null,
                'confirmed_at' => $row['status'] === 'confirmed' ? now() : null,
                'confirmed_by' => $row['status'] === 'confirmed' ? $admin->id : null,
            ]);
        }
    }

    private function payouts(array $customers, User $admin): void
    {
        foreach ([
            'buyer' => '0987654321',
            'seller' => '0987654322',
            'renter' => '0987654323',
            'lessor' => '0987654324',
            'dispute' => '0987654325',
        ] as $key => $accountNumber) {
            $customer = $customers[$key];
            CustomerVerification::query()->updateOrCreate(
                ['customer_id' => $customer->id],
                [
                    'status' => 'verified',
                    'document_type' => 'citizen_id',
                    'document_number' => 'DEMO-'.strtoupper($key),
                    'submitted_at' => now()->subDay(),
                    'verified_at' => now(),
                    'verified_by' => $admin->id,
                    'review_note' => 'Hồ sơ demo dùng cho browser smoke và transactional E2E.',
                ],
            );

            CustomerPayoutAccount::query()->updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'bank_code' => 'MB',
                    'account_number' => $accountNumber,
                ],
                [
                    'bank_name' => 'MB BANK',
                    'account_name' => mb_strtoupper($customer->name),
                    'status' => 'verified',
                    'is_default' => true,
                    'verified_at' => now(),
                    'verified_by' => $admin->id,
                    'review_note' => 'Tài khoản demo cố định.',
                ],
            );
        }

        $seller = $customers['seller'];
        $account = CustomerPayoutAccount::query()
            ->where('customer_id', $seller->id)
            ->where('is_default', true)
            ->firstOrFail();
        $service = app(WithdrawalService::class);

        $service->submit(
            $seller->id,
            $account->id,
            '300000.00',
            'Yêu cầu demo chờ duyệt.',
            'demo-withdrawal-submitted',
        );
        $paid = $service->submit(
            $seller->id,
            $account->id,
            '250000.00',
            'Yêu cầu demo đã chi.',
            'demo-withdrawal-paid',
        );
        if ($paid->status === 'submitted') {
            $paid = $service->approve($paid, $admin->id);
        }
        if ($paid->status === 'approved') {
            $service->markPaid($paid, $admin->id, 'DEMO-PAYOUT-PAID');
        }
    }

    private function products(array $customers, User $admin): array
    {
        $rows = [
            'sale' => ['code' => 'NSO-0102', 'name' => 'Ninja School Kunai cấp 119', 'owner' => 'seller', 'modes' => ['sell'], 'sale_price' => 850000, 'approval_status' => 'approved', 'is_published' => true],
            'rental' => ['code' => 'NSO-0201', 'name' => 'Ninja School Tone cấp 110', 'owner' => 'lessor', 'modes' => ['rent'], 'rental_price' => 120000, 'approval_status' => 'approved', 'is_published' => true],
            'installment' => ['code' => 'NRO-0301', 'name' => 'Ngọc Rồng máy chủ 3', 'owner' => 'seller', 'modes' => ['sell'], 'sale_price' => 2400000, 'installment_enabled' => true, 'approval_status' => 'approved', 'is_published' => true],
            'installment_history' => ['code' => 'NRO-0302', 'name' => 'Ngọc Rồng lịch sử trả góp', 'owner' => 'seller', 'modes' => ['sell'], 'sale_price' => 2400000, 'installment_enabled' => true, 'approval_status' => 'pending', 'is_published' => false],
            'completed' => ['code' => 'AVA-0401', 'name' => 'Avatar 250 ô đất', 'owner' => 'seller', 'modes' => ['sell'], 'sale_price' => 650000, 'approval_status' => 'pending', 'is_published' => false],
            'active_rental' => ['code' => 'NSO-0501', 'name' => 'Ninja School Sanzu cấp 125', 'owner' => 'lessor', 'modes' => ['rent'], 'rental_price' => 180000, 'approval_status' => 'pending', 'is_published' => false],
            'returned_rental_history' => ['code' => 'NSO-0202', 'name' => 'Ninja School lịch sử hoàn trả', 'owner' => 'lessor', 'modes' => ['rent'], 'rental_price' => 120000, 'approval_status' => 'pending', 'is_published' => false],
            'disputed' => ['code' => 'NRO-0601', 'name' => 'Ngọc Rồng máy chủ 6', 'owner' => 'seller', 'modes' => ['sell'], 'sale_price' => 3200000, 'approval_status' => 'pending', 'is_published' => false],
            'pending' => ['code' => 'AVA-0701', 'name' => 'Avatar VIP 400 ô đất', 'owner' => 'seller', 'modes' => ['sell', 'rent'], 'sale_price' => 1500000, 'rental_price' => 180000, 'approval_status' => 'pending', 'is_published' => false],
            'rejected' => ['code' => 'NSO-0801', 'name' => 'Ninja School chưa đủ bằng chứng', 'owner' => 'seller', 'modes' => ['sell'], 'sale_price' => 420000, 'approval_status' => 'rejected', 'is_published' => false],
        ];

        foreach ($rows as $key => $row) {
            $modes = $row['modes'];
            $owner = $customers[$row['owner']];
            unset($row['modes'], $row['owner']);
            $product = Product::query()->updateOrCreate(['code' => $row['code']], [
                ...$row,
                'slug' => Str::slug($row['name'].'-'.$row['code']),
                'product_type' => 'game_account',
                'game_code' => str_starts_with($row['code'], 'AVA') ? 'avatar' : (str_starts_with($row['code'], 'NRO') ? 'dragon_ball' : 'ninja_school'),
                'server_name' => 'Server demo',
                'status' => 'active',
                'availability_status' => 'available',
                'description' => 'Dữ liệu mẫu Product → Transaction.',
                'owner_customer_id' => $owner->id,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
                'published_at' => $row['is_published'] ? now() : null,
                'approved_at' => $row['approval_status'] === 'approved' ? now() : null,
                'approved_by' => $row['approval_status'] === 'approved' ? $admin->id : null,
            ]);
            $product->syncOfferModes($modes);
            if (in_array('rent', $modes, true)) {
                $product->rentalRates()->updateOrCreate(['label' => '1 ngày'], [
                    'period_unit' => 'day', 'period_count' => 1, 'price' => $product->rental_price ?? 120000,
                    'deposit_amount' => 500000, 'is_default' => true, 'is_active' => true,
                ]);
            }
            $rows[$key] = $product;
        }

        return $rows;
    }

    private function transactions(array $products, array $customers, User $admin): array
    {
        $rows = [
            'installment' => ['code' => 'TRX-DEMO-INSTALLMENT', 'product' => 'installment_history', 'buyer' => 'buyer', 'seller' => 'seller', 'transaction_type' => 'purchase', 'purchase_mode' => 'installment', 'value' => 2400000, 'deposit' => 0, 'paid' => 800000, 'status' => 'partially_paid'],
            'completed' => ['code' => 'TRX-DEMO-COMPLETED-SALE', 'product' => 'completed', 'buyer' => 'buyer', 'seller' => 'seller', 'transaction_type' => 'purchase', 'purchase_mode' => 'full', 'value' => 650000, 'deposit' => 0, 'paid' => 650000, 'status' => 'completed'],
            'active_rental' => ['code' => 'TRX-DEMO-ACTIVE-RENTAL', 'product' => 'active_rental', 'buyer' => 'renter', 'seller' => 'lessor', 'transaction_type' => 'rental', 'purchase_mode' => 'full', 'value' => 540000, 'deposit' => 700000, 'paid' => 1240000, 'status' => 'active'],
            'returned' => ['code' => 'TRX-DEMO-RETURNED-RENTAL', 'product' => 'returned_rental_history', 'buyer' => 'buyer', 'seller' => 'lessor', 'transaction_type' => 'rental', 'purchase_mode' => 'full', 'value' => 240000, 'deposit' => 500000, 'paid' => 740000, 'status' => 'returned'],
            'disputed' => ['code' => 'TRX-DEMO-DISPUTE-OPEN', 'product' => 'disputed', 'buyer' => 'dispute', 'seller' => 'seller', 'transaction_type' => 'purchase', 'purchase_mode' => 'deposit', 'value' => 3200000, 'deposit' => 800000, 'paid' => 800000, 'status' => 'disputed'],
            'cancelled' => ['code' => 'TRX-DEMO-CANCELLED', 'product' => 'sale', 'buyer' => 'renter', 'seller' => 'seller', 'transaction_type' => 'purchase', 'purchase_mode' => 'full', 'value' => 850000, 'deposit' => 0, 'paid' => 0, 'status' => 'cancelled'],
        ];

        foreach ($rows as $key => $row) {
            $total = $row['value'] + $row['deposit'];
            $transaction = Transaction::query()->updateOrCreate(['code' => $row['code']], [
                'product_id' => $products[$row['product']]->id,
                'buyer_customer_id' => $customers[$row['buyer']]->id,
                'seller_customer_id' => $customers[$row['seller']]->id,
                'transaction_type' => $row['transaction_type'],
                'purchase_mode' => $row['purchase_mode'],
                'transaction_value' => $row['value'],
                'deposit_amount' => $row['deposit'],
                'initial_payment_amount' => $row['paid'],
                'installment_count' => $row['purchase_mode'] === 'installment' ? 3 : null,
                'total_payable' => $total,
                'paid_amount' => $row['paid'],
                'escrow_amount' => $row['paid'],
                'released_amount' => $row['status'] === 'completed' ? $row['paid'] : 0,
                'seller_fee_amount' => 0,
                'seller_net_amount' => $row['value'],
                'transaction_date' => now()->toDateString(),
                'due_date' => now()->addDays(7)->toDateString(),
                'rental_start_at' => $row['transaction_type'] === 'rental' ? now()->subDay() : null,
                'rental_end_at' => $row['transaction_type'] === 'rental' ? now()->addDays(2) : null,
                'status' => $row['status'],
                'payment_method' => 'bank',
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);
            $rows[$key] = $transaction;
        }

        return $rows;
    }

    private function payments(array $transactions, array $customers, User $admin): void
    {
        $rows = [
            ['transaction' => 'installment', 'code' => 'PAY-DEMO-I1', 'number' => 1, 'amount' => 800000, 'status' => 'confirmed'],
            ['transaction' => 'installment', 'code' => 'PAY-DEMO-I2', 'number' => 2, 'amount' => 800000, 'status' => 'submitted'],
            ['transaction' => 'installment', 'code' => 'PAY-DEMO-I3', 'number' => 3, 'amount' => 800000, 'status' => 'pending'],
            ['transaction' => 'completed', 'code' => 'PAY-DEMO-SALE', 'number' => null, 'amount' => 650000, 'status' => 'confirmed'],
            ['transaction' => 'active_rental', 'code' => 'PAY-DEMO-RENT', 'number' => null, 'amount' => 1240000, 'status' => 'confirmed'],
            ['transaction' => 'returned', 'code' => 'PAY-DEMO-RETURN', 'number' => null, 'amount' => 740000, 'status' => 'confirmed'],
            ['transaction' => 'disputed', 'code' => 'PAY-DEMO-DISPUTE', 'number' => null, 'amount' => 800000, 'status' => 'confirmed'],
        ];

        foreach ($rows as $row) {
            $transaction = $transactions[$row['transaction']];
            TransactionPayment::query()->updateOrCreate(['code' => $row['code']], [
                'transaction_id' => $transaction->id,
                'customer_id' => $transaction->buyer_customer_id,
                'payment_type' => $row['number'] ? 'installment' : 'full',
                'component_type' => 'principal',
                'installment_number' => $row['number'],
                'amount' => $row['amount'],
                'payment_method' => 'bank',
                'status' => $row['status'],
                'due_date' => now()->addDays(7)->toDateString(),
                'confirmed_at' => $row['status'] === 'confirmed' ? now() : null,
                'confirmed_by' => $row['status'] === 'confirmed' ? $admin->id : null,
            ]);
        }
    }

    private function eventsAndCheckpoints(array $transactions, array $customers): void
    {
        foreach (['completed', 'active_rental', 'returned', 'disputed'] as $key) {
            TransactionCheckpoint::query()->firstOrCreate([
                'transaction_id' => $transactions[$key]->id, 'checkpoint' => 'seller_handover',
            ], ['customer_id' => $transactions[$key]->seller_customer_id, 'actor_type' => 'seller', 'actor_id' => $transactions[$key]->seller_customer_id, 'confirmed_at' => now()]);
        }
        TransactionEvent::query()->firstOrCreate([
            'transaction_id' => $transactions['cancelled']->id, 'event_type' => 'cancelled',
        ], ['actor_type' => 'admin', 'title' => 'Giao dịch đã hủy']);
    }

    private function disputes(array $transactions, array $customers, User $admin): void
    {
        MarketplaceDispute::query()->updateOrCreate(['code' => 'DSP-DEMO-OPEN'], [
            'transaction_id' => $transactions['disputed']->id, 'opened_by_customer_id' => $customers['dispute']->id,
            'reason' => 'not_as_described', 'description' => 'Không đúng mô tả', 'status' => 'open',
        ]);
        MarketplaceDispute::query()->updateOrCreate(['code' => 'DSP-DEMO-RESOLVED'], [
            'transaction_id' => $transactions['completed']->id, 'opened_by_customer_id' => $customers['buyer']->id,
            'reason' => 'other', 'description' => 'Đã xử lý', 'status' => 'resolved', 'resolution' => 'Hai bên thống nhất', 'resolved_at' => now(), 'resolved_by' => $admin->id,
        ]);
    }

    private function notifications(array $transactions, array $customers): void
    {
        $rows = [
            ['buyer', 'payment_due', 'Sắp đến hạn thanh toán'],
            ['seller', 'transaction_completed', 'Giao dịch đã hoàn tất'],
            ['renter', 'rental_active', 'Giao dịch thuê đang hoạt động'],
            ['lessor', 'rental_returned', 'Tài sản đã được hoàn trả'],
            ['dispute', 'dispute_opened', 'Tranh chấp đã được tiếp nhận'],
        ];
        foreach ($rows as [$customer, $type, $title]) {
            MarketplaceNotification::query()->create([
                'customer_id' => $customers[$customer]->id, 'type' => $type, 'title' => $title,
                'message' => $title, 'action_url' => '/account/transactions', 'data' => ['demo' => true],
            ]);
        }
    }
}
