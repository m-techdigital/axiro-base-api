<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Models\MarketplaceDispute;
use App\Models\MarketplaceNotification;
use App\Models\ListingRentalRate;
use App\Models\Product;
use App\Models\ProductListing;
use App\Models\Transaction;
use App\Models\TransactionCheckpoint;
use App\Models\TransactionEvent;
use App\Models\TransactionPayment;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Marketplace\MarketplaceFeeCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketplaceDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $admin = User::query()->where('username', env('ADMIN_USERNAME', 'admin'))->firstOrFail();

            $customers = $this->customers();
            $this->wallets($customers, $admin);

            $products = $this->products($customers, $admin);
            $listings = $this->listings($products, $customers, $admin);

            $transactions = $this->transactions($products, $listings, $customers, $admin);
            $this->payments($transactions, $customers, $admin);
            $this->contracts($transactions, $admin);
            $this->events($transactions, $customers, $admin);
            $this->checkpoints($transactions, $customers);
            $this->disputes($transactions, $customers, $admin);
            $this->notifications($transactions, $listings, $customers);
        });
    }

    /** @return array<string, Customer> */
    private function customers(): array
    {
        $rows = [
            'buyer' => ['code' => 'CUS-DEMO', 'username' => env('CUSTOMER_USERNAME', 'customer'), 'name' => env('CUSTOMER_NAME', 'Khách mua mẫu'), 'email' => env('CUSTOMER_EMAIL', 'customer@example.com'), 'phone' => env('CUSTOMER_PHONE', '0900000000')],
            'seller' => ['code' => 'CUS-SELLER', 'username' => 'seller', 'name' => 'Người bán mẫu', 'email' => 'seller@example.com', 'phone' => '0911111111'],
            'renter' => ['code' => 'CUS-RENTER', 'username' => 'renter', 'name' => 'Người thuê mẫu', 'email' => 'renter@example.com', 'phone' => '0922222222'],
            'lessor' => ['code' => 'CUS-LESSOR', 'username' => 'lessor', 'name' => 'Người cho thuê mẫu', 'email' => 'lessor@example.com', 'phone' => '0933333333'],
            'dispute' => ['code' => 'CUS-DISPUTE', 'username' => 'dispute', 'name' => 'Khách khiếu nại mẫu', 'email' => 'dispute@example.com', 'phone' => '0944444444'],
        ];

        return collect($rows)->mapWithKeys(function (array $row, string $key): array {
            $customer = Customer::query()->updateOrCreate(
                ['username' => $row['username']],
                [...$row, 'password' => env('DEMO_CUSTOMER_PASSWORD', 'change-me'), 'status' => 'active'],
            );

            return [$key => $customer];
        })->all();
    }

    /** @param array<string, Customer> $customers */
    private function wallets(array $customers, User $admin): void
    {
        WalletTransaction::query()->whereIn('code', ['WAL-DEMO-002', 'WAL-DEMO-003'])->delete();

        $balances = [
            'buyer' => 2_450_000,
            'seller' => 4_820_000,
            'renter' => 1_300_000,
            'lessor' => 3_100_000,
            'dispute' => 880_000,
        ];

        foreach ($balances as $key => $balance) {
            CustomerWallet::query()->updateOrCreate(
                ['customer_id' => $customers[$key]->id],
                ['available_balance' => $balance, 'held_balance' => $key === 'dispute' ? 500_000 : 0],
            );
        }

        $rows = [
            ['code' => 'WAL-DEMO-001', 'customer' => 'buyer', 'type' => 'deposit', 'direction' => 'credit', 'amount' => 1_000_000, 'balance_after' => 2_450_000, 'available_before' => 1_450_000, 'available_after' => 2_450_000, 'held_before' => 0, 'held_after' => 0, 'balance_bucket' => 'available', 'occurred_at' => now()->subDays(8), 'status' => 'confirmed', 'payment_method' => 'bank', 'external_reference' => 'VCB-DEMO-001', 'confirmed_at' => now()->subDays(8), 'note' => 'Nạp tiền ngân hàng đã được quản trị viên xác nhận.'],
            ['code' => 'NAP-DEMO-PENDING', 'customer' => 'buyer', 'type' => 'deposit_request', 'direction' => 'credit', 'amount' => 500_000, 'balance_after' => 2_450_000, 'available_before' => 2_450_000, 'available_after' => 2_450_000, 'held_before' => 0, 'held_after' => 0, 'balance_bucket' => 'available', 'occurred_at' => now()->subDays(2), 'submitted_at' => now()->subDays(2), 'status' => 'submitted', 'payment_method' => 'bank', 'external_reference' => 'VCB-DEMO-PENDING', 'note' => 'Yêu cầu nạp tiền đang chờ đối soát; chưa làm thay đổi số dư.'],
            ['code' => 'NAP-DEMO-REJECTED', 'customer' => 'renter', 'type' => 'deposit_request', 'direction' => 'credit', 'amount' => 200_000, 'balance_after' => 1_300_000, 'available_before' => 1_300_000, 'available_after' => 1_300_000, 'held_before' => 0, 'held_after' => 0, 'balance_bucket' => 'available', 'occurred_at' => now()->subDay(), 'submitted_at' => now()->subDays(2), 'status' => 'rejected', 'payment_method' => 'bank', 'external_reference' => 'BANK-DEMO-REJECT', 'review_note' => 'Không tìm thấy giao dịch khớp với chứng từ đã gửi.', 'note' => 'Yêu cầu nạp tiền bị từ chối; chưa làm thay đổi số dư.'],
        ];

        foreach ($rows as $row) {
            $customer = $customers[$row['customer']];
            unset($row['customer']);
            WalletTransaction::query()->updateOrCreate(
                ['code' => $row['code']],
                [...$row, 'customer_id' => $customer->id, 'confirmed_by' => $row['status'] === 'confirmed' ? $admin->id : null],
            );
        }
    }

    /** @param array<string, Customer> $customers @return array<string, Product> */
    private function products(array $customers, User $admin): array
    {
        $rows = [
            'sale_available' => ['code' => 'NSO-0102', 'name' => 'Ninja School Kunai cấp 119', 'game_code' => 'ninja_school', 'server_name' => 'Kunai', 'level' => 119, 'price' => 850000, 'owner' => 'seller', 'image' => '/images/mock/accounts/ninja-1.jpg', 'attributes' => ['class' => 'Kunai', 'weapon' => '15', 'gender' => 'Nam']],
            'rental_available' => ['code' => 'NSO-0201', 'name' => 'Ninja School Tone cấp 110', 'game_code' => 'ninja_school', 'server_name' => 'Tone', 'level' => 110, 'price' => 1200000, 'owner' => 'lessor', 'image' => '/images/mock/accounts/ninja-2.jpg', 'attributes' => ['class' => 'Tone', 'weapon' => '14', 'gender' => 'Nữ']],
            'installment' => ['code' => 'NRO-0301', 'name' => 'Ngọc Rồng máy chủ 3 sức mạnh 80 tỷ', 'game_code' => 'dragon_ball', 'server_name' => 'Máy chủ 3', 'level' => 80, 'price' => 2400000, 'owner' => 'seller', 'image' => '/images/mock/accounts/dragon-1.jpg', 'attributes' => ['planet' => 'Trái Đất', 'power' => '80 tỷ', 'disciple' => 'Có']],
            'completed_sale' => ['code' => 'AVA-0401', 'name' => 'Avatar 250 ô đất', 'game_code' => 'avatar', 'server_name' => 'Thành phố diệu kỳ', 'level' => 72, 'price' => 650000, 'owner' => 'seller', 'image' => '/images/mock/accounts/avatar-1.jpg', 'attributes' => ['land_slots' => 250, 'gender' => 'Nữ']],
            'active_rental' => ['code' => 'NSO-0501', 'name' => 'Ninja School Sanzu cấp 125', 'game_code' => 'ninja_school', 'server_name' => 'Sanzu', 'level' => 125, 'price' => 1800000, 'owner' => 'lessor', 'image' => '/images/mock/accounts/ninja-3.jpg', 'attributes' => ['class' => 'Sanzu', 'weapon' => '16', 'gender' => 'Nam']],
            'disputed_sale' => ['code' => 'NRO-0601', 'name' => 'Ngọc Rồng máy chủ 6 sức mạnh 120 tỷ', 'game_code' => 'dragon_ball', 'server_name' => 'Máy chủ 6', 'level' => 120, 'price' => 3200000, 'owner' => 'seller', 'image' => '/images/mock/accounts/dragon-2.jpg', 'attributes' => ['planet' => 'Xayda', 'power' => '120 tỷ', 'disciple' => 'Có']],
            'pending_review' => ['code' => 'AVA-0701', 'name' => 'Avatar VIP 400 ô đất', 'game_code' => 'avatar', 'server_name' => 'Thành phố diệu kỳ', 'level' => 85, 'price' => 1500000, 'owner' => 'seller', 'image' => '/images/mock/accounts/avatar-2.jpg', 'attributes' => ['land_slots' => 400, 'gender' => 'Nam']],
            'rejected' => ['code' => 'NSO-0801', 'name' => 'Ninja School chưa đủ bằng chứng', 'game_code' => 'ninja_school', 'server_name' => 'Bokken', 'level' => 90, 'price' => 420000, 'owner' => 'seller', 'image' => '/images/mock/accounts/ninja-3.jpg', 'attributes' => ['class' => 'Bokken', 'weapon' => '12', 'gender' => 'Nam']],
        ];

        return collect($rows)->mapWithKeys(function (array $row, string $key) use ($customers, $admin): array {
            $owner = $customers[$row['owner']];
            $image = $row['image'];
            unset($row['owner'], $row['image']);
            $product = Product::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    ...$row,
                    'slug' => Str::slug($row['name'].'-'.$row['code']),
                    'product_type' => 'game_account',
                    'status' => 'active',
                    'description' => 'Dữ liệu mẫu được tạo theo vòng đời canonical của AXIRO để kiểm thử trên MBN và AXIRO Admin.',
                    'image_url' => $image,
                    'image_urls' => [$image],
                    'owner_customer_id' => $owner->id,
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );

            return [$key => $product];
        })->all();
    }

    /** @param array<string, Product> $products @param array<string, Customer> $customers @return array<string, ProductListing> */
    private function listings(array $products, array $customers, User $admin): array
    {
        $rows = [
            'sale_available' => ['code' => 'LST-NSO-0102', 'product' => 'sale_available', 'owner' => 'seller', 'listing_type' => 'sale', 'status' => 'published', 'title' => 'Bán Ninja School Kunai cấp 119', 'sale_price' => 850000, 'deposit_amount' => 200000, 'allow_installment' => true, 'max_installment_count' => 3, 'minimum_initial_payment' => 350000],
            'rental_available' => ['code' => 'LST-NSO-0201', 'product' => 'rental_available', 'owner' => 'lessor', 'listing_type' => 'rental', 'status' => 'published', 'title' => 'Cho thuê Ninja School Tone cấp 110', 'rental_price' => 120000, 'rental_price_unit' => 'day', 'minimum_rental_period' => 2, 'deposit_amount' => 500000],
            'installment' => ['code' => 'LST-NRO-0301', 'product' => 'installment', 'owner' => 'seller', 'listing_type' => 'sale', 'status' => 'reserved', 'title' => 'Ngọc Rồng trả góp ba kỳ', 'sale_price' => 2400000, 'deposit_amount' => 600000, 'allow_installment' => true, 'max_installment_count' => 3, 'minimum_initial_payment' => 800000],
            'completed_sale' => ['code' => 'LST-AVA-0401', 'product' => 'completed_sale', 'owner' => 'seller', 'listing_type' => 'sale', 'status' => 'completed', 'title' => 'Avatar đã bán hoàn tất', 'sale_price' => 650000, 'deposit_amount' => 0],
            'active_rental' => ['code' => 'LST-NSO-0501', 'product' => 'active_rental', 'owner' => 'lessor', 'listing_type' => 'rental', 'status' => 'reserved', 'title' => 'Ninja School đang được thuê', 'rental_price' => 180000, 'rental_price_unit' => 'day', 'minimum_rental_period' => 3, 'deposit_amount' => 700000],
            'disputed_sale' => ['code' => 'LST-NRO-0601', 'product' => 'disputed_sale', 'owner' => 'seller', 'listing_type' => 'sale', 'status' => 'reserved', 'title' => 'Ngọc Rồng đang tranh chấp', 'sale_price' => 3200000, 'deposit_amount' => 800000],
            'pending_review' => ['code' => 'LST-AVA-0701', 'product' => 'pending_review', 'owner' => 'seller', 'listing_type' => 'sale', 'status' => 'pending_review', 'title' => 'Avatar VIP chờ duyệt', 'sale_price' => 1500000, 'deposit_amount' => 300000],
            'rejected' => ['code' => 'LST-NSO-0801', 'product' => 'rejected', 'owner' => 'seller', 'listing_type' => 'sale', 'status' => 'rejected', 'title' => 'Ninja School bị từ chối', 'sale_price' => 420000, 'deposit_amount' => 100000, 'rejection_reason' => 'Thiếu video hiện trạng và bằng chứng quyền sở hữu tài khoản.'],
        ];

        return collect($rows)->mapWithKeys(function (array $row, string $key) use ($products, $customers, $admin): array {
            $product = $products[$row['product']];
            $owner = $customers[$row['owner']];
            unset($row['product'], $row['owner']);
            $approved = in_array($row['status'], ['published', 'reserved', 'completed'], true);
            $listing = ProductListing::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    ...$row,
                    'product_id' => $product->id,
                    'owner_customer_id' => $owner->id,
                    'description' => 'Tin đăng mẫu để kiểm thử duyệt tin, mua bán, thuê, thanh toán và tranh chấp.',
                    'rental_period_unit' => ($row['listing_type'] ?? null) === 'rental' ? 'day' : null,
                    'rental_billing_mode' => ($row['listing_type'] ?? null) === 'rental' ? 'periodic' : 'upfront',
                    'rental_billing_cycle_unit' => ($row['listing_type'] ?? null) === 'rental' ? 'day' : null,
                    'rental_billing_cycle_count' => ($row['listing_type'] ?? null) === 'rental' ? 1 : null,
                    'installment_interval_unit' => 'week',
                    'installment_interval_count' => 1,
                    'published_at' => $approved ? now()->subDays(12) : null,
                    'approved_at' => $approved ? now()->subDays(12) : null,
                    'approved_by' => $approved ? $admin->id : null,
                ],
            );
            if (($row['listing_type'] ?? null) === 'rental') {
                $listing->rentalRates()->delete();
                foreach ([
                    ['label'=>'6 giờ','period_unit'=>'hour','period_count'=>6,'price'=>45000,'deposit_amount'=>$row['deposit_amount'] ?? 500000,'is_default'=>false,'sort_order'=>1,'is_active'=>true],
                    ['label'=>'1 ngày','period_unit'=>'day','period_count'=>1,'price'=>$row['rental_price'] ?? 120000,'deposit_amount'=>$row['deposit_amount'] ?? 500000,'is_default'=>true,'sort_order'=>2,'is_active'=>true],
                    ['label'=>'3 ngày','period_unit'=>'day','period_count'=>3,'price'=>($row['rental_price'] ?? 120000)*2.5,'deposit_amount'=>$row['deposit_amount'] ?? 500000,'is_default'=>false,'sort_order'=>3,'is_active'=>true],
                    ['label'=>'1 tuần','period_unit'=>'week','period_count'=>1,'price'=>($row['rental_price'] ?? 120000)*5,'deposit_amount'=>$row['deposit_amount'] ?? 500000,'is_default'=>false,'sort_order'=>4,'is_active'=>true],
                ] as $rate) $listing->rentalRates()->create($rate);
            }

            return [$key => $listing];
        })->all();
    }

    /** @param array<string, Product> $products @param array<string, ProductListing> $listings @param array<string, Customer> $customers @return array<string, Transaction> */
    private function transactions(array $products, array $listings, array $customers, User $admin): array
    {
        $today = CarbonImmutable::today();
        $rows = [
            'installment' => ['code' => 'TRX-DEMO-INSTALLMENT', 'product' => 'installment', 'listing' => 'installment', 'buyer' => 'buyer', 'seller' => 'seller', 'transaction_type' => 'purchase', 'purchase_mode' => 'installment', 'transaction_value' => 2400000, 'deposit_amount' => 0, 'initial_payment_amount' => 800000, 'installment_count' => 3, 'total_payable' => 2400000, 'paid_amount' => 800000, 'status' => 'partially_paid', 'transaction_date' => $today->subDays(8), 'due_date' => $today->addDays(22), 'note' => 'Giao dịch trả góp mẫu: kỳ đầu đã xác nhận, kỳ hai đã gửi chứng từ, kỳ ba chưa đến hạn.'],
            'completed_sale' => ['code' => 'TRX-DEMO-COMPLETED-SALE', 'product' => 'completed_sale', 'listing' => 'completed_sale', 'buyer' => 'buyer', 'seller' => 'seller', 'transaction_type' => 'purchase', 'purchase_mode' => 'full', 'transaction_value' => 650000, 'deposit_amount' => 0, 'initial_payment_amount' => 650000, 'installment_count' => null, 'total_payable' => 650000, 'paid_amount' => 650000, 'status' => 'completed', 'transaction_date' => $today->subDays(30), 'due_date' => $today->subDays(29), 'handed_over_at' => now()->subDays(29), 'completed_at' => now()->subDays(28), 'note' => 'Mua bán đã hoàn tất, có hợp đồng và đầy đủ nhật ký bàn giao.'],
            'active_rental' => ['code' => 'TRX-DEMO-ACTIVE-RENTAL', 'product' => 'active_rental', 'listing' => 'active_rental', 'buyer' => 'renter', 'seller' => 'lessor', 'transaction_type' => 'rental', 'purchase_mode' => 'full', 'transaction_value' => 540000, 'deposit_amount' => 700000, 'initial_payment_amount' => 1240000, 'installment_count' => null, 'total_payable' => 1240000, 'paid_amount' => 1240000, 'status' => 'active', 'transaction_date' => $today->subDays(2), 'due_date' => $today->addDay(), 'rental_start_at' => now()->subDay(), 'rental_end_at' => now()->addDays(2), 'handed_over_at' => now()->subDay(), 'note' => 'Giao dịch thuê đang hoạt động, đã thanh toán tiền thuê và tiền cọc.'],
            'returned_rental' => ['code' => 'TRX-DEMO-RETURNED-RENTAL', 'product' => 'rental_available', 'listing' => 'rental_available', 'buyer' => 'buyer', 'seller' => 'lessor', 'transaction_type' => 'rental', 'purchase_mode' => 'full', 'transaction_value' => 240000, 'deposit_amount' => 500000, 'initial_payment_amount' => 740000, 'installment_count' => null, 'total_payable' => 740000, 'paid_amount' => 740000, 'status' => 'returned', 'transaction_date' => $today->subDays(10), 'due_date' => $today->subDays(5), 'rental_start_at' => now()->subDays(9), 'rental_end_at' => now()->subDays(7), 'handed_over_at' => now()->subDays(9), 'returned_at' => now()->subDays(7), 'note' => 'Tài khoản thuê đã hoàn trả, đang chờ hoàn tất đối soát tiền cọc.'],
            'disputed_sale' => ['code' => 'TRX-DEMO-DISPUTE-OPEN', 'product' => 'disputed_sale', 'listing' => 'disputed_sale', 'buyer' => 'dispute', 'seller' => 'seller', 'transaction_type' => 'purchase', 'purchase_mode' => 'deposit', 'transaction_value' => 3200000, 'deposit_amount' => 800000, 'initial_payment_amount' => 800000, 'installment_count' => null, 'total_payable' => 4000000, 'paid_amount' => 800000, 'status' => 'disputed', 'transaction_date' => $today->subDays(5), 'due_date' => $today->addDays(3), 'handed_over_at' => now()->subDays(3), 'note' => 'Khách hàng báo tài khoản không đúng mô tả sau bàn giao thử.'],
            'cancelled' => ['code' => 'TRX-DEMO-CANCELLED', 'product' => 'sale_available', 'listing' => null, 'buyer' => 'renter', 'seller' => 'seller', 'transaction_type' => 'purchase', 'purchase_mode' => 'full', 'transaction_value' => 850000, 'deposit_amount' => 0, 'initial_payment_amount' => 0, 'installment_count' => null, 'total_payable' => 850000, 'paid_amount' => 0, 'status' => 'cancelled', 'transaction_date' => $today->subDays(15), 'due_date' => $today->subDays(14), 'note' => 'Khách hàng hủy trước khi thanh toán; tin đăng được mở lại.'],
        ];

        return collect($rows)->mapWithKeys(function (array $row, string $key) use ($products, $listings, $customers, $admin): array {
            $product = $products[$row['product']];
            $listing = $row['listing'] ? $listings[$row['listing']] : null;
            $buyer = $customers[$row['buyer']];
            $seller = $customers[$row['seller']];
            unset($row['product'], $row['listing'], $row['buyer'], $row['seller']);
            $fee = app(MarketplaceFeeCalculator::class)->calculate(
                (string) $row['transaction_type'],
                (string) $row['transaction_value'],
            );
            $discount = 0;
            $totalPayable = (float) $row['transaction_value']
                + (float) $fee['service_fee']
                + (float) $fee['buyer_fee_amount']
                + (float) $fee['tax_amount']
                + (float) $row['deposit_amount']
                - $discount;

            $transaction = Transaction::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    ...$row,
                    'listing_id' => $listing?->id,
                    'product_id' => $product->id,
                    'buyer_customer_id' => $buyer->id,
                    'seller_customer_id' => $seller->id,
                    'service_fee' => $fee['service_fee'],
                    'buyer_fee_amount' => $fee['buyer_fee_amount'],
                    'seller_fee_amount' => $fee['seller_fee_amount'],
                    'tax_amount' => $fee['tax_amount'],
                    'seller_net_amount' => $fee['seller_net_amount'],
                    'fee_policy_version' => $fee['fee_policy_version'],
                    'fee_snapshot' => $fee['fee_snapshot'],
                    'total_payable' => $totalPayable,
                    'discount' => $discount,
                    'refunded_amount' => 0,
                    'escrow_amount' => $row['paid_amount'],
                    'released_amount' => $row['status'] === 'completed' ? $row['paid_amount'] : 0,
                    'wallet_paid_amount' => 0,
                    'installment_interval_unit' => ($row['purchase_mode'] ?? null) === 'installment' ? 'week' : null,
                    'installment_interval_count' => ($row['purchase_mode'] ?? null) === 'installment' ? 1 : null,
                    'rental_period_unit' => ($row['transaction_type'] ?? null) === 'rental' ? 'day' : null,
                    'rental_period_count' => ($row['transaction_type'] ?? null) === 'rental' ? max(1, (int) now()->diffInDays($row['rental_end_at'] ?? now()->addDay())) : null,
                    'rental_billing_mode' => ($row['transaction_type'] ?? null) === 'rental' ? 'periodic' : null,
                    'rental_billing_cycle_unit' => ($row['transaction_type'] ?? null) === 'rental' ? 'day' : null,
                    'rental_billing_cycle_count' => ($row['transaction_type'] ?? null) === 'rental' ? 1 : null,
                    'next_payment_due_at' => $row['due_date'],
                    'payment_method' => 'bank',
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );

            return [$key => $transaction];
        })->all();
    }

    /** @param array<string, Transaction> $transactions @param array<string, Customer> $customers */
    private function payments(array $transactions, array $customers, User $admin): void
    {
        $rows = [
            ['code' => 'PAY-INST-01', 'transaction' => 'installment', 'customer' => 'buyer', 'type' => 'installment', 'number' => 1, 'amount' => 800000, 'status' => 'confirmed', 'due' => now()->subDays(7), 'reference' => 'VCB-INST-01'],
            ['code' => 'PAY-INST-02', 'transaction' => 'installment', 'customer' => 'buyer', 'type' => 'installment', 'number' => 2, 'amount' => 800000, 'status' => 'submitted', 'due' => now()->addDays(7), 'reference' => 'VCB-INST-02'],
            ['code' => 'PAY-INST-03', 'transaction' => 'installment', 'customer' => 'buyer', 'type' => 'installment', 'number' => 3, 'amount' => 800000, 'status' => 'pending', 'due' => now()->addDays(22), 'reference' => null],
            ['code' => 'PAY-COMPLETE-01', 'transaction' => 'completed_sale', 'customer' => 'buyer', 'type' => 'full', 'number' => 1, 'amount' => 650000, 'status' => 'confirmed', 'due' => now()->subDays(29), 'reference' => 'MOMO-COMPLETE'],
            ['code' => 'PAY-RENT-01', 'transaction' => 'active_rental', 'customer' => 'renter', 'type' => 'full', 'number' => 1, 'amount' => 1240000, 'status' => 'confirmed', 'due' => now()->subDays(2), 'reference' => 'VCB-RENT-01'],
            ['code' => 'PAY-RETURN-01', 'transaction' => 'returned_rental', 'customer' => 'buyer', 'type' => 'full', 'number' => 1, 'amount' => 740000, 'status' => 'confirmed', 'due' => now()->subDays(10), 'reference' => 'VCB-RETURN-01'],
            ['code' => 'PAY-DISPUTE-01', 'transaction' => 'disputed_sale', 'customer' => 'dispute', 'type' => 'deposit', 'number' => 1, 'amount' => 800000, 'status' => 'confirmed', 'due' => now()->subDays(5), 'reference' => 'VCB-DISPUTE-01'],
        ];

        foreach ($rows as $row) {
            $confirmed = $row['status'] === 'confirmed';
            TransactionPayment::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'transaction_id' => $transactions[$row['transaction']]->id,
                    'customer_id' => $customers[$row['customer']]->id,
                    'payment_type' => $row['type'],
                    'component_type' => str_contains($row['type'], 'rent') ? 'rental_fee' : ($row['type'] === 'deposit' ? 'principal' : 'principal'),
                    'cycle_number' => str_contains($row['type'], 'rent') ? ($row['number'] ?? 1) : null,
                    'refundable' => false,
                    'installment_number' => $row['number'],
                    'amount' => $row['amount'],
                    'payment_method' => 'bank',
                    'status' => $row['status'],
                    'settlement_status' => $confirmed ? (($row['transaction'] === 'completed_sale') ? 'released' : 'held') : 'unsettled',
                    'reference' => $row['reference'],
                    'due_date' => $row['due'],
                    'paid_at' => in_array($row['status'], ['submitted', 'confirmed'], true) ? now()->subHours(4) : null,
                    'confirmed_at' => $confirmed ? now()->subHours(2) : null,
                    'settled_at' => $confirmed ? now()->subHours(2) : null,
                    'released_at' => $row['transaction'] === 'completed_sale' ? now()->subDays(28) : null,
                    'confirmed_by' => $confirmed ? $admin->id : null,
                    'note' => 'Khoản thanh toán mẫu đồng bộ giữa MBN và AXIRO Admin.',
                ],
            );
        }
    }

    /** @param array<string, Transaction> $transactions */
    private function contracts(array $transactions, User $admin): void
    {
        $rows = [
            ['code' => 'CTR-DEMO-SALE', 'transaction' => 'completed_sale', 'type' => 'sale', 'title' => 'Hợp đồng mua bán Avatar mẫu', 'value' => 650000, 'deposit' => 0, 'status' => 'completed', 'start' => now()->subDays(29), 'end' => now()->subDays(28)],
            ['code' => 'CTR-DEMO-RENTAL', 'transaction' => 'active_rental', 'type' => 'rental', 'title' => 'Hợp đồng thuê Ninja School mẫu', 'value' => 540000, 'deposit' => 700000, 'status' => 'active', 'start' => now()->subDay(), 'end' => now()->addDays(2)],
        ];

        foreach ($rows as $row) {
            Contract::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'transaction_id' => $transactions[$row['transaction']]->id,
                    'contract_type' => $row['type'],
                    'title' => $row['title'],
                    'contract_value' => $row['value'],
                    'deposit_amount' => $row['deposit'],
                    'signed_at' => $row['start'],
                    'start_date' => $row['start'],
                    'end_date' => $row['end'],
                    'status' => $row['status'],
                    'note' => 'Hợp đồng mẫu được tạo theo mô hình Contract của AXIRO cha.',
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );
        }
    }

    /** @param array<string, Transaction> $transactions @param array<string, Customer> $customers */
    private function events(array $transactions, array $customers, User $admin): void
    {
        $definitions = [
            'installment' => [
                ['created', 'customer', $customers['buyer']->id, 'Đã tạo giao dịch trả góp', 'Khách hàng chọn trả góp ba kỳ.'],
                ['payment_confirmed', 'user', $admin->id, 'Đã xác nhận kỳ thanh toán đầu', 'Quản trị viên đã đối soát khoản thanh toán 800.000 đồng.'],
                ['payment_submitted', 'customer', $customers['buyer']->id, 'Đã gửi chứng từ kỳ hai', 'Khoản thanh toán đang chờ quản trị viên xác nhận.'],
            ],
            'completed_sale' => [
                ['created', 'customer', $customers['buyer']->id, 'Đã tạo đơn mua', null],
                ['payment_confirmed', 'user', $admin->id, 'Đã xác nhận thanh toán', null],
                ['handed_over', 'customer', $customers['seller']->id, 'Đã bàn giao tài khoản', 'Người bán đã bàn giao thông tin tài khoản.'],
                ['completed', 'customer', $customers['buyer']->id, 'Đã hoàn tất giao dịch', 'Người mua xác nhận thông tin đúng mô tả.'],
            ],
            'active_rental' => [
                ['created', 'customer', $customers['renter']->id, 'Đã tạo đơn thuê', null],
                ['payment_confirmed', 'user', $admin->id, 'Đã xác nhận tiền thuê và tiền cọc', null],
                ['handed_over', 'customer', $customers['lessor']->id, 'Đã bàn giao tài khoản thuê', null],
                ['rental_active', 'system', null, 'Thời gian thuê đang hoạt động', 'Tài khoản cần được hoàn trả đúng hạn.'],
            ],
            'returned_rental' => [
                ['handed_over', 'customer', $customers['lessor']->id, 'Đã bàn giao tài khoản thuê', null],
                ['returned', 'customer', $customers['buyer']->id, 'Người thuê đã hoàn trả', 'Đang chờ người cho thuê đối soát hiện trạng.'],
            ],
            'disputed_sale' => [
                ['created', 'customer', $customers['dispute']->id, 'Đã tạo đơn đặt cọc', null],
                ['handed_over', 'customer', $customers['seller']->id, 'Đã bàn giao thử tài khoản', null],
                ['dispute_opened', 'customer', $customers['dispute']->id, 'Đã mở khiếu nại', 'Khách hàng cho rằng sức mạnh thực tế không đúng mô tả.'],
            ],
            'cancelled' => [
                ['created', 'customer', $customers['renter']->id, 'Đã tạo giao dịch', null],
                ['cancelled', 'user', $admin->id, 'Giao dịch đã được đóng', 'Quản trị viên đóng giao dịch trước khi phát sinh thanh toán; tin đăng được mở lại.'],
            ],
        ];

        foreach ($definitions as $key => $events) {
            $transaction = $transactions[$key];
            TransactionEvent::query()->where('transaction_id', $transaction->id)->delete();
            foreach ($events as $index => [$type, $actorType, $actorId, $title, $description]) {
                TransactionEvent::query()->create([
                    'transaction_id' => $transaction->id,
                    'event_type' => $type,
                    'actor_type' => $actorType,
                    'actor_id' => $actorId,
                    'title' => $title,
                    'description' => $description,
                    'metadata' => ['demo' => true, 'sequence' => $index + 1],
                    'created_at' => now()->subHours(count($events) - $index),
                    'updated_at' => now()->subHours(count($events) - $index),
                ]);
            }
        }
    }


    /** @param array<string, Transaction> $transactions @param array<string, Customer> $customers */
    private function checkpoints(array $transactions, array $customers): void
    {
        $rows = [
            ['transaction'=>'completed_sale','checkpoint'=>'seller_handover','customer'=>'seller','hours'=>30],
            ['transaction'=>'completed_sale','checkpoint'=>'buyer_received','customer'=>'buyer','hours'=>29],
            ['transaction'=>'active_rental','checkpoint'=>'seller_handover','customer'=>'lessor','hours'=>12],
            ['transaction'=>'active_rental','checkpoint'=>'buyer_received','customer'=>'renter','hours'=>11],
            ['transaction'=>'returned_rental','checkpoint'=>'renter_returned','customer'=>'buyer','hours'=>5],
            ['transaction'=>'returned_rental','checkpoint'=>'lessor_received_return','customer'=>'lessor','hours'=>4],
        ];
        foreach ($rows as $row) {
            TransactionCheckpoint::query()->updateOrCreate(
                ['transaction_id'=>$transactions[$row['transaction']]->id,'checkpoint'=>$row['checkpoint']],
                ['customer_id'=>$customers[$row['customer']]->id,'actor_type'=>'customer','actor_id'=>$customers[$row['customer']]->id,'note'=>'Mốc xác nhận dữ liệu mẫu.','metadata'=>['demo'=>true],'confirmed_at'=>now()->subHours($row['hours'])],
            );
        }
    }

    /** @param array<string, Transaction> $transactions @param array<string, ProductListing> $listings @param array<string, Customer> $customers */
    private function notifications(array $transactions, array $listings, array $customers): void
    {
        MarketplaceNotification::query()->whereJsonContains('data->demo', true)->delete();
        $rows = [
            ['customer'=>'seller','type'=>'listing_pending','title'=>'Tin đăng đang chờ duyệt','message'=>'Tin '.$listings['pending_review']->code.' đang chờ quản trị viên kiểm tra.','url'=>'/account/listings'],
            ['customer'=>'seller','type'=>'listing_rejected','title'=>'Tin đăng cần chỉnh sửa','message'=>'Tin '.$listings['rejected']->code.' bị từ chối vì thiếu bằng chứng sở hữu.','url'=>'/account/listings'],
            ['customer'=>'buyer','type'=>'payment_submitted','title'=>'Khoản trả góp đang đối soát','message'=>'Kỳ thanh toán thứ hai của '.$transactions['installment']->code.' đang chờ quản trị viên xác nhận.','url'=>'/account/purchases/'.$transactions['installment']->id],
            ['customer'=>'renter','type'=>'rental_active','title'=>'Tài khoản thuê đang hoạt động','message'=>'Hãy hoàn trả đúng hạn và giữ nguyên hiện trạng tài khoản.','url'=>'/account/purchases/'.$transactions['active_rental']->id],
            ['customer'=>'dispute','type'=>'dispute_opened','title'=>'Tranh chấp đang được xử lý','message'=>'Yêu cầu của giao dịch '.$transactions['disputed_sale']->code.' đã được tiếp nhận.','url'=>'/account/purchases/'.$transactions['disputed_sale']->id],
        ];
        foreach($rows as $row){
            MarketplaceNotification::create(['customer_id'=>$customers[$row['customer']]->id,'type'=>$row['type'],'title'=>$row['title'],'message'=>$row['message'],'action_url'=>$row['url'],'data'=>['demo'=>true],'read_at'=>null]);
        }
    }

    /** @param array<string, Transaction> $transactions @param array<string, Customer> $customers */
    private function disputes(array $transactions, array $customers, User $admin): void
    {
        MarketplaceDispute::query()->updateOrCreate(
            ['code' => 'DSP-DEMO-OPEN'],
            [
                'transaction_id' => $transactions['disputed_sale']->id,
                'opened_by_customer_id' => $customers['dispute']->id,
                'reason' => 'not_as_described',
                'status' => 'open',
                'description' => 'Sức mạnh và vật phẩm thực tế khác với thông tin trong tin đăng. Khách hàng yêu cầu tạm giữ giao dịch để đối chiếu.',
                'evidence' => ['/demo/evidence/listing.png', '/demo/evidence/handover-video.mp4'],
                'resolution' => null,
                'resolved_at' => null,
                'resolved_by' => null,
            ],
        );

        MarketplaceDispute::query()->updateOrCreate(
            ['code' => 'DSP-DEMO-RESOLVED'],
            [
                'transaction_id' => $transactions['completed_sale']->id,
                'opened_by_customer_id' => $customers['buyer']->id,
                'reason' => 'cannot_login',
                'status' => 'resolved',
                'description' => 'Khách hàng từng không đăng nhập được sau bàn giao.',
                'evidence' => ['/demo/evidence/login-error.png'],
                'resolution' => 'Người bán đã hỗ trợ gỡ thiết bị cũ và đổi thông tin bảo mật. Khách hàng đăng nhập thành công và xác nhận hoàn tất.',
                'resolved_at' => now()->subDays(28),
                'resolved_by' => $admin->id,
            ],
        );
    }
}
