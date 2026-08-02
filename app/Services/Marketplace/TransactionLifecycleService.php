<?php

namespace App\Services\Marketplace;

use App\Enums\DisputeOutcome;
use App\Enums\ProductAvailabilityStatus;
use App\Enums\ProductSelectionContext;
use App\Models\AuditLog;
use App\Models\MarketplaceDispute;
use App\Models\MarketplacePlatformLedgerEntry;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionCheckpoint;
use App\Models\TransactionEvent;
use App\Models\TransactionPayment;
use App\Services\ProductAvailabilityService;
use App\Services\ProductSelectionService;
use App\Services\Wallet\WalletLedgerService;
use App\Support\Marketplace\MoneyMath;
use App\Support\Marketplace\TransactionIdempotency;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TransactionLifecycleService
{
    public function __construct(private MarketplaceNotificationService $notifications, private WalletLedgerService $wallets, private MarketplaceFeeCalculator $fees, private ProductAvailabilityService $availability, private ProductSelectionService $selection) {}

    public function createFromProduct(Product $product, int $buyerId, array $data): Transaction
    {
        return DB::transaction(function () use ($product, $buyerId, $data) {
            $locked = Product::with('rentalRates')->lockForUpdate()->findOrFail($product->id);
            $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
            $requestHash = TransactionIdempotency::hash($buyerId, $locked->id, $data);
            $existing = Transaction::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                if ((int) $existing->buyer_customer_id !== $buyerId || ! hash_equals((string) $existing->request_hash, $requestHash)) {
                    $this->recordCheckoutIdempotencyAudit('checkout_idempotency_conflict', $existing, $buyerId, $idempotencyKey, $requestHash);
                    throw ValidationException::withMessages(['idempotency_key' => 'Khóa chống trùng đã được dùng với một yêu cầu khác.']);
                }

                $this->recordCheckoutIdempotencyAudit('checkout_idempotent_replay', $existing, $buyerId, $idempotencyKey, $requestHash);

                return $this->load($existing);
            }
            if ((int) ($data['availability_version'] ?? 0) !== (int) $locked->availability_version) {
                throw ValidationException::withMessages(['availability_version' => 'Trạng thái sản phẩm đã thay đổi. Hãy tải lại trước khi tạo giao dịch.']);
            }
            $this->selection->assertSelectable($locked, ProductSelectionContext::TRANSACTION, ($data['transaction_type'] ?? 'sale') === 'rental' ? 'rent' : 'sell');
            if ($locked->owner_customer_id === $buyerId) {
                throw ValidationException::withMessages(['product' => 'Bạn không thể giao dịch với sản phẩm của chính mình.']);
            }
            $requestedType = $data['transaction_type'] ?? 'sale';
            $isRental = $requestedType === 'rental';
            $mode = $isRental ? 'rental' : ($data['purchase_mode'] ?? 'full');
            if (! $locked->supports($isRental ? 'rental' : 'sale')) {
                throw ValidationException::withMessages(['transaction_type' => 'Sản phẩm không hỗ trợ loại giao dịch đã chọn.']);
            }
            if ($mode === 'installment' && ! $locked->installment_enabled) {
                throw ValidationException::withMessages(['purchase_mode' => 'Sản phẩm này không hỗ trợ trả góp.']);
            }
            [$value,$deposit,$rentalMeta] = $isRental ? $this->resolveRentalPricing($locked, $data) : [(string) $locked->sale_price, (string) ($locked->sale_deposit_amount ?? 0), []];
            $fee = $this->fees->calculate($isRental ? 'rental' : 'purchase', $value);
            $total = MoneyMath::add($value, $deposit, $fee['buyer_fee_amount'], $fee['tax_amount']);
            $initial = match ($mode) {
                'deposit' => MoneyMath::max($deposit, $data['initial_payment_amount'] ?? 0),
                'installment' => MoneyMath::max($locked->minimum_initial_payment ?? 0, $data['initial_payment_amount'] ?? 0),
                default => $isRental ? MoneyMath::add($rentalMeta['first_due_amount'] ?? $total, $fee['buyer_fee_amount'], $fee['tax_amount']) : $total
            };
            if (bccomp($initial, $total, 2) > 0) {
                throw ValidationException::withMessages(['initial_payment_amount' => 'Khoản thanh toán ban đầu không được vượt tổng tiền.']);
            }
            $transaction = Transaction::create([
                'code' => 'TRX-'.strtoupper(Str::random(10)), 'idempotency_key' => $idempotencyKey, 'request_hash' => $requestHash, 'transaction_type' => $isRental ? 'rental' : 'purchase', 'purchase_mode' => $mode,
                'product_id' => $locked->id, 'buyer_customer_id' => $buyerId, 'seller_customer_id' => $locked->owner_customer_id,
                'transaction_value' => $value, 'service_fee' => $fee['service_fee'], 'buyer_fee_amount' => $fee['buyer_fee_amount'], 'seller_fee_amount' => $fee['seller_fee_amount'], 'tax_amount' => $fee['tax_amount'], 'seller_net_amount' => $fee['seller_net_amount'], 'fee_policy_version' => $fee['fee_policy_version'], 'fee_snapshot' => $fee['fee_snapshot'], 'discount' => 0, 'deposit_amount' => $deposit, 'initial_payment_amount' => $initial,
                'installment_count' => $mode === 'installment' ? ($data['installment_count'] ?? $locked->max_installment_count ?? 2) : null,
                'installment_interval_unit' => $mode === 'installment' ? ($data['installment_interval_unit'] ?? $locked->installment_interval_unit ?? 'week') : null,
                'installment_interval_count' => $mode === 'installment' ? ($data['installment_interval_count'] ?? $locked->installment_interval_count ?? 1) : null,
                'rental_period_unit' => $rentalMeta['period_unit'] ?? null, 'rental_period_count' => $rentalMeta['period_count'] ?? null,
                'rental_billing_mode' => $rentalMeta['billing_mode'] ?? null, 'rental_billing_cycle_unit' => $rentalMeta['billing_cycle_unit'] ?? null, 'rental_billing_cycle_count' => $rentalMeta['billing_cycle_count'] ?? null,
                'total_payable' => $total, 'paid_amount' => 0, 'refunded_amount' => 0, 'escrow_amount' => 0, 'released_amount' => 0, 'wallet_paid_amount' => 0,
                'transaction_date' => now()->toDateString(), 'due_date' => $data['due_date'] ?? null, 'next_payment_due_at' => now()->toDateString(),
                'rental_start_at' => $rentalMeta['start_at'] ?? null, 'rental_end_at' => $rentalMeta['end_at'] ?? null, 'status' => 'pending_payment', 'payment_method' => $data['payment_method'] ?? null, 'note' => $data['note'] ?? null,
            ]);
            $this->createPaymentPlan($transaction, $rentalMeta);
            $this->event($transaction, 'created', 'customer', $buyerId, 'Đã tạo giao dịch', 'Giao dịch đã được tạo và đang chờ thanh toán. Sản phẩm chỉ được giữ chỗ khi thanh toán được xác nhận.');
            $this->notifications->transaction($transaction->seller_customer_id, 'transaction_created', 'Có giao dịch mới', 'Một khách hàng đã tạo giao dịch từ sản phẩm '.$locked->code.'.', $transaction->id, $transaction->code);
            $this->notifications->transaction($transaction->buyer_customer_id, 'transaction_created', 'Đã tạo giao dịch', 'Giao dịch '.$transaction->code.' đã được tạo và đang chờ thanh toán.', $transaction->id, $transaction->code);

            return $this->load($transaction);
        });
    }

    private function resolveRentalPricing(Product $product, array $data): array
    {
        $rate = null;
        if (! empty($data['rental_rate_id'])) {
            $rate = $product->rentalRates->firstWhere('id', (int) $data['rental_rate_id']);
        }
        if (! $rate) {
            $rate = $product->rentalRates->where('is_active', true)->firstWhere('is_default', true) ?? $product->rentalRates->where('is_active', true)->first();
        }
        $unit = $rate?->period_unit ?? ($data['rental_period_unit'] ?? $product->rental_period_unit ?? $product->rental_price_unit ?? 'day');
        $count = (int) ($rate?->period_count ?? $data['rental_period_count'] ?? $data['rental_period'] ?? $product->minimum_rental_period ?? 1);
        $count = max(1, $count);
        $price = (string) ($rate?->price ?? bcmul((string) $product->rental_price, (string) $count, 2));
        $deposit = (string) ($rate?->deposit_amount ?? $product->rental_deposit_amount ?? 0);
        $start = Carbon::parse($data['rental_start_at'] ?? now());
        $end = $this->addPeriod($start->copy(), $unit, $count);
        $billingMode = $data['rental_billing_mode'] ?? $product->rental_billing_mode ?? 'upfront';
        $cycleUnit = $data['rental_billing_cycle_unit'] ?? $product->rental_billing_cycle_unit ?? $unit;
        $cycleCount = max(1, (int) ($data['rental_billing_cycle_count'] ?? $product->rental_billing_cycle_count ?? 1));
        $cycles = $billingMode === 'periodic' ? max(1, (int) ceil($this->unitValue($unit, $count) / max(1, $this->unitValue($cycleUnit, $cycleCount)))) : 1;
        $firstFee = $cycles > 1 ? bcdiv($price, (string) $cycles, 2) : $price;

        return [$price, $deposit, ['period_unit' => $unit, 'period_count' => $count, 'start_at' => $start, 'end_at' => $end, 'billing_mode' => $billingMode, 'billing_cycle_unit' => $cycleUnit, 'billing_cycle_count' => $cycleCount, 'cycle_count' => $cycles, 'first_due_amount' => bcadd($firstFee, $deposit, 2)]];
    }

    private function unitValue(string $unit, int $count): int
    {
        return match ($unit) {
            'hour' => $count,'day' => $count * 24,'week' => $count * 168,'month' => $count * 720,default => $count * 24
        };
    }

    private function addPeriod(Carbon $date, string $unit, int $count): Carbon
    {
        return match ($unit) {
            'hour' => $date->addHours($count),'week' => $date->addWeeks($count),'month' => $date->addMonthsNoOverflow($count),default => $date->addDays($count)
        };
    }

    private function createPaymentPlan(Transaction $t, array $rentalMeta = []): void
    {
        if ($t->transaction_type === 'rental') {
            $this->createRentalPlan($t, $rentalMeta);

            return;
        }
        if ($t->purchase_mode === 'installment') {
            $count = max(2, (int) $t->installment_count);
            $first = (string) $t->initial_payment_amount;
            TransactionPayment::create($this->paymentData($t, 'installment', 'principal', 1, null, $first, now(), false));
            $remaining = bcsub((string) $t->total_payable, $first, 2);
            $per = bcdiv($remaining, (string) ($count - 1), 2);
            $allocated = '0.00';
            for ($n = 2; $n <= $count; $n++) {
                $amount = $n === $count ? bcsub($remaining, $allocated, 2) : $per;
                $allocated = bcadd($allocated, $amount, 2);
                $due = $this->addPeriod(now(), $t->installment_interval_unit ?? 'week', ($n - 1) * max(1, (int) $t->installment_interval_count));
                TransactionPayment::create($this->paymentData($t, 'installment', 'principal', $n, null, $amount, $due, false));
            }
            $this->syncNextDue($t);

            return;
        }
        $type = $t->purchase_mode === 'deposit' ? 'deposit' : 'full';
        TransactionPayment::create($this->paymentData($t, $type, 'principal', null, null, (string) $t->initial_payment_amount, now(), false));
        if ($t->purchase_mode === 'deposit') {
            $remaining = bcsub((string) $t->total_payable, (string) $t->initial_payment_amount, 2);
            if (bccomp($remaining, '0.00', 2) > 0) {
                TransactionPayment::create($this->paymentData($t, 'final', 'principal', null, null, $remaining, now()->addDays(7), false));
            }
        }
        $this->syncNextDue($t);
    }

    private function createRentalPlan(Transaction $t, array $meta): void
    {
        if (bccomp((string) $t->deposit_amount, '0.00', 2) > 0) {
            TransactionPayment::create($this->paymentData($t, 'security_deposit', 'security_deposit', null, 0, (string) $t->deposit_amount, now(), true, $t->rental_start_at?->toDateString(), $t->rental_end_at?->toDateString()));
        }
        $cycles = max(1, (int) ($meta['cycle_count'] ?? 1));
        $total = (string) $t->transaction_value;
        $per = bcdiv($total, (string) $cycles, 2);
        $allocated = '0.00';
        $start = Carbon::parse($t->rental_start_at ?? now());
        for ($n = 1; $n <= $cycles; $n++) {
            $amount = $n === $cycles ? bcsub($total, $allocated, 2) : $per;
            $allocated = bcadd($allocated, $amount, 2);
            if ($n === 1) {
                $amount = bcadd($amount, bcadd((string) $t->buyer_fee_amount, (string) $t->tax_amount, 2), 2);
            }$periodStart = $n === 1 ? $start->copy() : $this->addPeriod($start->copy(), $t->rental_billing_cycle_unit ?? $t->rental_period_unit ?? 'day', ($n - 1) * max(1, (int) ($t->rental_billing_cycle_count ?? 1)));
            $periodEnd = $this->addPeriod($periodStart->copy(), $t->rental_billing_cycle_unit ?? $t->rental_period_unit ?? 'day', max(1, (int) ($t->rental_billing_cycle_count ?? 1)));
            $due = $t->rental_billing_mode === 'upfront' ? now() : $periodStart->copy();
            TransactionPayment::create($this->paymentData($t, 'rental_cycle', 'rental_fee', null, $n, $amount, $due, false, $periodStart->toDateString(), (($t->rental_end_at && $periodEnd->greaterThan($t->rental_end_at)) ? $t->rental_end_at : $periodEnd)->toDateString()));
        }
        $this->syncNextDue($t);
    }

    private function paymentData(Transaction $t, string $type, string $component, ?int $installment, ?int $cycle, string $amount, $due, bool $refundable = false, ?string $periodStart = null, ?string $periodEnd = null): array
    {
        return ['code' => 'PAY-'.strtoupper(Str::random(10)), 'transaction_id' => $t->id, 'customer_id' => $t->buyer_customer_id, 'payment_type' => $type, 'component_type' => $component, 'installment_number' => $installment, 'cycle_number' => $cycle, 'amount' => $amount, 'refundable' => $refundable, 'status' => 'pending', 'settlement_status' => 'unsettled', 'period_start' => $periodStart, 'period_end' => $periodEnd, 'due_date' => Carbon::parse($due)->toDateString()];
    }

    public function submitPayment(TransactionPayment $payment, int $customerId, array $data): TransactionPayment
    {
        abort_unless($payment->customer_id === $customerId, 403);
        if (! in_array($payment->status, ['pending', 'rejected', 'overdue'], true)) {
            throw ValidationException::withMessages(['payment' => 'Khoản thanh toán không thể gửi lại.']);
        }
        if (($data['payment_method'] ?? null) === 'wallet') {
            return DB::transaction(function () use ($payment, $customerId, $data) {
                $locked = TransactionPayment::lockForUpdate()->findOrFail($payment->id);
                $transaction = Transaction::lockForUpdate()->findOrFail($locked->transaction_id);
                $this->reserveProductForPayment($transaction);
                $walletEntry = $this->wallets->debitAvailable($customerId, (string) $locked->amount, 'transaction_payment', ['idempotency_key' => 'payment:'.$locked->id.':buyer-debit', 'transaction_id' => $transaction->id, 'transaction_payment_id' => $locked->id, 'payment_method' => 'wallet', 'reference_type' => 'transaction_payment', 'reference_id' => $locked->id, 'note' => 'Thanh toán '.$locked->code]);
                $this->wallets->creditHeld($transaction->seller_customer_id, (string) $locked->amount, 'escrow_hold', ['idempotency_key' => 'payment:'.$locked->id.':seller-hold', 'transaction_id' => $transaction->id, 'transaction_payment_id' => $locked->id, 'payment_method' => 'wallet', 'reference_type' => 'transaction_payment', 'reference_id' => $locked->id, 'note' => 'Tạm giữ tiền giao dịch '.$transaction->code]);
                $locked->update(['status' => 'confirmed', 'payment_method' => 'wallet', 'reference' => $data['reference'] ?? $walletEntry->code, 'paid_at' => now(), 'confirmed_at' => now(), 'wallet_transaction_id' => $walletEntry->id, 'settlement_status' => 'held', 'settled_at' => now(), 'note' => $data['note'] ?? null]);
                $this->recalculatePaymentState($transaction);
                $this->event($transaction, 'payment_confirmed', 'customer', $customerId, 'Đã thanh toán bằng số dư ví', 'Khoản '.$locked->code.' đã được xác nhận tự động.', ['payment_id' => $locked->id]);

                return $locked->fresh();
            });
        }
        $payment->update(['status' => 'submitted', 'payment_method' => $data['payment_method'], 'reference' => $data['reference'] ?? null, 'paid_at' => now(), 'note' => $data['note'] ?? null]);
        $this->event($payment->transaction, 'payment_submitted', 'customer', $customerId, 'Đã gửi thông tin thanh toán', 'Khách hàng đã gửi thông tin thanh toán '.$payment->code.'.');

        return $payment->fresh();
    }

    public function confirmPayment(TransactionPayment $payment, int $adminId): TransactionPayment
    {
        return DB::transaction(function () use ($payment, $adminId) {
            $locked = TransactionPayment::lockForUpdate()->findOrFail($payment->id);
            if ($locked->status === 'confirmed') {
                return $locked;
            }if (! in_array($locked->status, ['pending', 'submitted'], true)) {
                throw ValidationException::withMessages(['payment' => 'Khoản thanh toán không ở trạng thái có thể xác nhận.']);
            }$transaction = Transaction::lockForUpdate()->findOrFail($locked->transaction_id);
            $this->reserveProductForPayment($transaction);
            $this->wallets->creditHeld($transaction->seller_customer_id, (string) $locked->amount, 'escrow_hold', ['idempotency_key' => 'payment:'.$locked->id.':seller-hold', 'transaction_id' => $transaction->id, 'transaction_payment_id' => $locked->id, 'payment_method' => $locked->payment_method ?? 'bank', 'reference_type' => 'transaction_payment', 'reference_id' => $locked->id, 'confirmed_by' => $adminId, 'note' => 'Đối soát thanh toán '.$locked->code]);
            $locked->update(['status' => 'confirmed', 'confirmed_at' => now(), 'confirmed_by' => $adminId, 'paid_at' => $locked->paid_at ?? now(), 'settlement_status' => 'held', 'settled_at' => now()]);
            $this->recalculatePaymentState($transaction);
            $this->event($transaction, 'payment_confirmed', 'user', $adminId, 'Đã xác nhận thanh toán', 'Khoản '.$locked->code.' đã được xác nhận.', ['payment_id' => $locked->id]);
            foreach ([$transaction->buyer_customer_id, $transaction->seller_customer_id] as $id) {
                $this->notifications->transaction($id, 'payment_confirmed', 'Thanh toán đã được xác nhận', 'Khoản '.$locked->code.' đã được đối soát.', $transaction->id, $transaction->code);
            }

            return $locked->fresh(['transaction']);
        });
    }

    private function reserveProductForPayment(Transaction $transaction): void
    {
        $product = Product::lockForUpdate()->findOrFail($transaction->product_id);
        $conflictingTransactionExists = Transaction::query()
            ->where('product_id', $product->id)
            ->where('id', '!=', $transaction->id)
            ->where('status', '!=', 'cancelled')
            ->whereHas('payments', fn ($query) => $query->where('status', 'confirmed'))
            ->exists();

        if ($conflictingTransactionExists || $product->approval_status !== 'approved' || ! $product->is_published || ! in_array($product->availability_status, ['available', 'held'], true) || ($product->availability_status === 'held' && (int) $product->held_by_transaction_id !== (int) $transaction->id)) {
            throw ValidationException::withMessages([
                'product' => 'Sản phẩm đã được giữ chỗ bởi một giao dịch khác.',
            ]);
        }

        if ($product->availability_status === 'available') {
            $this->availability->hold($product, $transaction->buyer_customer_id, $transaction, 30, 'Giữ chỗ khi xác nhận thanh toán');
        }
    }

    private function releaseProductAfterCancellation(Transaction $transaction): void
    {
        $product = Product::lockForUpdate()->find($transaction->product_id);
        if (! $product || $product->availability_status !== 'held') {
            return;
        }

        $anotherPaidTransactionExists = Transaction::query()
            ->where('product_id', $product->id)
            ->where('id', '!=', $transaction->id)
            ->where('status', '!=', 'cancelled')
            ->whereHas('payments', fn ($query) => $query->where('status', 'confirmed'))
            ->exists();

        if (! $anotherPaidTransactionExists) {
            $this->availability->transition($product, ProductAvailabilityStatus::AVAILABLE, $transaction, 'Giao dịch bị hủy');
        }
    }

    private function recalculatePaymentState(Transaction $t): void
    {
        $paid = (string) $t->payments()->where('status', 'confirmed')->sum('amount');
        $walletPaid = (string) $t->payments()->where('status', 'confirmed')->where('payment_method', 'wallet')->sum('amount');
        $paymentStatus = bccomp($paid, (string) $t->total_payable, 2) >= 0 ? 'paid' : 'partially_paid';
        $status = in_array($t->status, ['pending_payment', 'partially_paid', 'paid', 'overdue'], true) ? $paymentStatus : $t->status;
        $next = $t->payments()->whereIn('status', ['pending', 'rejected'])->orderBy('due_date')->value('due_date');
        $t->update(['paid_amount' => $paid, 'wallet_paid_amount' => $walletPaid, 'escrow_amount' => $paid, 'status' => $status, 'next_payment_due_at' => $next]);
    }

    private function syncNextDue(Transaction $t): void
    {
        $next = $t->payments()->where('status', 'pending')->orderBy('due_date')->value('due_date');
        $t->update(['next_payment_due_at' => $next]);
    }

    private function startObligationsSatisfied(Transaction $t): bool
    {
        return ! $t->payments()->whereIn('status', ['pending', 'rejected', 'overdue'])->where(function ($q) {
            $q->whereNull('due_date')->orWhereDate('due_date', '<=', today());
        })->exists();
    }

    public function allowedActions(Transaction $t, int $customerId): array
    {
        $buyer = $t->buyer_customer_id === $customerId;
        $seller = $t->seller_customer_id === $customerId;
        $a = [];
        if ($seller && in_array($t->status, ['paid', 'partially_paid'], true) && $this->startObligationsSatisfied($t)) {
            $a[] = 'seller_handover';
        }if ($buyer && $t->status === 'handover_pending') {
            $a[] = 'buyer_receive';
        }if ($buyer && $t->transaction_type === 'rental' && in_array($t->status, ['active', 'overdue'], true)) {
            $a[] = 'renter_return';
        }if ($seller && $t->transaction_type === 'rental' && $t->status === 'return_pending') {
            $a[] = 'lessor_receive_return';
        }if ($buyer && bccomp((string) $t->paid_amount, (string) $t->total_payable, 2) >= 0 && (($t->transaction_type === 'purchase' && $t->status === 'handed_over') || ($t->transaction_type === 'rental' && $t->status === 'returned'))) {
            $a[] = 'complete';
        }if (($buyer || $seller) && ! in_array($t->status, ['completed', 'cancelled'], true) && ! $t->disputes()->where('status', 'open')->exists()) {
            $a[] = 'open_dispute';
        }

        return $a;
    }

    public function transition(Transaction $transaction, string $action, string $actorType, int $actorId): Transaction
    {
        return DB::transaction(function () use ($transaction, $action, $actorType, $actorId) {
            $t = Transaction::lockForUpdate()->findOrFail($transaction->id);
            if ($actorType === 'customer' && ! in_array($action, $this->allowedActions($t, $actorId), true)) {
                throw ValidationException::withMessages(['action' => 'Bạn không có quyền thực hiện hành động này ở trạng thái hiện tại.']);
            }$next = $t->status;
            $checkpoint = null;
            $title = 'Đã cập nhật giao dịch';
            if ($action === 'seller_handover') {
                $next = 'handover_pending';
                $checkpoint = 'seller_handover';
                $title = 'Bên giao đã xác nhận bàn giao';
            } elseif ($action === 'buyer_receive') {
                $next = $t->transaction_type === 'rental' ? 'active' : 'handed_over';
                $checkpoint = 'buyer_received';
                $title = 'Bên nhận đã xác nhận nhận tài khoản';
            } elseif ($action === 'renter_return') {
                $next = 'return_pending';
                $checkpoint = 'renter_returned';
                $title = 'Người thuê đã gửi yêu cầu hoàn trả';
            } elseif ($action === 'lessor_receive_return') {
                $next = 'returned';
                $checkpoint = 'lessor_received_return';
                $title = 'Người cho thuê đã xác nhận hoàn trả';
            } elseif ($action === 'complete') {
                $next = 'completed';
                $title = 'Giao dịch đã hoàn tất';
            } elseif ($action === 'cancel') {
                $next = 'cancelled';
                $title = 'Giao dịch đã hủy';
            } else {
                throw ValidationException::withMessages(['action' => 'Hành động không hợp lệ.']);
            }$updates = ['status' => $next];
            if (in_array($next, ['handover_pending', 'handed_over', 'active'], true) && ! $t->handed_over_at) {
                $updates['handed_over_at'] = now();
            }if ($next === 'returned') {
                $updates['returned_at'] = now();
            }if ($next === 'completed') {
                $updates['completed_at'] = now();
            }$t->update($updates);
            if ($checkpoint) {
                TransactionCheckpoint::updateOrCreate(['transaction_id' => $t->id, 'checkpoint' => $checkpoint], ['customer_id' => $actorType === 'customer' ? $actorId : null, 'actor_type' => $actorType, 'actor_id' => $actorId, 'confirmed_at' => now()]);
            }if ($next === 'completed') {
                $this->settleCompleted($t);
                $t->product && $this->availability->transition($t->product, $t->transaction_type === 'rental' ? ProductAvailabilityStatus::RENTED : ProductAvailabilityStatus::SOLD, $t, 'Giao dịch hoàn tất');
            }if ($next === 'cancelled') {
                $this->releaseProductAfterCancellation($t);
            }$this->event($t, $action, $actorType, $actorId, $title, null, ['checkpoint' => $checkpoint]);

            return $this->load($t);
        });
    }

    private function settleCompleted(Transaction $t): void
    {
        $payments = $t->payments()->where('status', 'confirmed')->where('settlement_status', 'held')->get();
        $gross = '0.00';
        $refunded = '0.00';
        foreach ($payments as $p) {
            if ($t->transaction_type === 'rental' && $p->refundable) {
                $ctx = ['idempotency_key' => 'payment:'.$p->id.':deposit-refund', 'transaction_id' => $t->id, 'transaction_payment_id' => $p->id, 'reference_type' => 'transaction_payment', 'reference_id' => $p->id];
                $this->wallets->transferHeldToAvailable($t->seller_customer_id, $t->buyer_customer_id, (string) $p->amount, 'rental_deposit_refund', $ctx);
                $refunded = bcadd($refunded, (string) $p->amount, 2);
                $p->update(['settlement_status' => 'refunded', 'released_at' => now()]);
            } else {
                $gross = bcadd($gross, (string) $p->amount, 2);
            }
        }
        $released = '0.00';
        if (bccomp($gross, '0.00', 2) > 0) {
            $net = (string) $t->seller_net_amount;
            [,,$platformFee] = $this->wallets->settleHeldWithFee($t->seller_customer_id, $gross, $net, ['idempotency_key' => 'transaction:'.$t->id.':net-settlement', 'transaction_id' => $t->id, 'reference_type' => 'transaction', 'reference_id' => $t->id, 'note' => 'Quyết toán ròng giao dịch '.$t->code]);
            MarketplacePlatformLedgerEntry::firstOrCreate(['idempotency_key' => 'transaction:'.$t->id.':platform-fee'], ['code' => 'PLF-'.strtoupper(Str::random(10)), 'transaction_id' => $t->id, 'type' => 'marketplace_fee', 'amount' => $platformFee, 'metadata' => ['buyer_fee_amount' => $t->buyer_fee_amount, 'seller_fee_amount' => $t->seller_fee_amount, 'tax_amount' => $t->tax_amount, 'fee_policy_version' => $t->fee_policy_version], 'occurred_at' => now()]);
            $t->payments()->where('status', 'confirmed')->where('settlement_status', 'held')->where('refundable', false)->update(['settlement_status' => 'released', 'released_at' => now()]);
            $released = $net;
        }
        $t->update(['released_amount' => bcadd((string) $t->released_amount, $released, 2), 'refunded_amount' => bcadd((string) $t->refunded_amount, $refunded, 2), 'escrow_amount' => '0.00']);
    }

    public function allowedAdminActions(Transaction $transaction): array
    {
        $actions = [];

        if (in_array($transaction->status, ['paid', 'partially_paid', 'handover_pending'], true)) {
            $actions[] = 'force_handover';
        }
        if ($transaction->transaction_type === 'rental'
            && in_array($transaction->status, ['active', 'return_pending', 'overdue'], true)) {
            $actions[] = 'force_return';
        }
        if (in_array($transaction->status, ['handed_over', 'returned'], true)) {
            $actions[] = 'complete';
        }
        if (! in_array($transaction->status, ['completed', 'cancelled'], true)) {
            $actions[] = 'cancel';
        }
        if ($transaction->status === 'cancelled') {
            $actions[] = 'reopen';
        }

        return $actions;
    }

    public function adminTransition(Transaction $transaction, string $action, int $adminId, ?string $note = null): Transaction
    {
        return DB::transaction(function () use ($transaction, $action, $adminId, $note) {
            $t = Transaction::lockForUpdate()->findOrFail($transaction->id);
            if (! in_array($action, $this->allowedAdminActions($t), true)) {
                throw ValidationException::withMessages(['action' => 'Hành động không còn hợp lệ ở trạng thái hiện tại.']);
            }
            $map = ['force_handover' => $t->transaction_type === 'rental' ? 'active' : 'handed_over', 'force_return' => 'returned', 'complete' => 'completed', 'cancel' => 'cancelled', 'reopen' => 'pending_payment'];
            if (! isset($map[$action])) {
                throw ValidationException::withMessages(['action' => 'Hành động quản trị không hợp lệ.']);
            }
            $updates = ['status' => $map[$action]];
            if ($map[$action] === 'completed') {
                $updates['completed_at'] = now();
            } elseif ($action === 'reopen') {
                $updates['completed_at'] = null;
            }
            $t->update($updates);
            if ($map[$action] === 'completed') {
                $this->settleCompleted($t);
                if ($t->product) {
                    $this->availability->transition($t->product, $t->transaction_type === 'rental' ? ProductAvailabilityStatus::RENTED : ProductAvailabilityStatus::SOLD, $t, 'Quản trị viên hoàn tất giao dịch');
                }
            }if ($map[$action] === 'cancelled') {
                $this->refundHeldPayments($t, 'admin_cancel');
                $this->releaseProductAfterCancellation($t);
            }$this->event($t, 'admin_'.$action, 'user', $adminId, 'Quản trị viên cập nhật giao dịch', $note);

            return $this->load($t);
        });
    }

    private function refundHeldPayments(Transaction $t, string $reason): void
    {
        $payments = $t->payments()->where('status', 'confirmed')->where('settlement_status', 'held')->get();
        $refunded = '0.00';
        foreach ($payments as $p) {
            $ctx = ['idempotency_key' => 'payment:'.$p->id.':refund:'.$reason, 'transaction_id' => $t->id, 'transaction_payment_id' => $p->id, 'reference_type' => 'transaction_payment', 'reference_id' => $p->id, 'note' => 'Hoàn tiền giao dịch '.$t->code];
            $this->wallets->transferHeldToAvailable($t->seller_customer_id, $t->buyer_customer_id, (string) $p->amount, 'transaction_refund', $ctx);
            $p->update(['settlement_status' => 'refunded', 'released_at' => now()]);
            $refunded = bcadd($refunded, (string) $p->amount, 2);
        }
        if (bccomp($refunded, '0.00', 2) > 0) {
            $t->update(['refunded_amount' => bcadd((string) $t->refunded_amount, $refunded, 2), 'escrow_amount' => '0.00']);
        }
    }

    public function openDispute(Transaction $t, int $customerId, array $data): MarketplaceDispute
    {
        abort_unless(in_array($customerId, [$t->buyer_customer_id, $t->seller_customer_id], true), 403);
        $d = MarketplaceDispute::create(['code' => 'DSP-'.strtoupper(Str::random(10)), 'transaction_id' => $t->id, 'opened_by_customer_id' => $customerId, 'reason' => $data['reason'], 'status' => 'open', 'description' => $data['description'], 'evidence' => $data['evidence'] ?? []]);
        $t->update(['status' => 'disputed']);
        $this->event($t, 'dispute_opened', 'customer', $customerId, 'Đã mở yêu cầu tranh chấp', $data['description']);

        return $d->fresh(['transaction', 'openedBy:id,code,name']);
    }

    public function resolveDispute(MarketplaceDispute $d, int $adminId, array $data): MarketplaceDispute
    {
        return DB::transaction(function () use ($d, $adminId, $data) {
            $lockedDispute = MarketplaceDispute::query()->lockForUpdate()->findOrFail($d->id);
            $transaction = Transaction::query()->lockForUpdate()->findOrFail($lockedDispute->transaction_id);

            if (in_array($lockedDispute->status, ['resolved', 'rejected', 'cancelled'], true)) {
                return $lockedDispute->fresh(['transaction', 'openedBy:id,code,name']);
            }

            $outcome = DisputeOutcome::from($data['outcome']);
            $next = match ($outcome) {
                DisputeOutcome::COMPLETE => 'completed',
                DisputeOutcome::CANCEL_REFUND, DisputeOutcome::CANCEL_NO_REFUND => 'cancelled',
                DisputeOutcome::REOPEN => $transaction->payments()->where('status', 'confirmed')->exists() ? 'paid' : 'pending_payment',
            };

            if ($data['status'] === 'rejected' && $outcome !== DisputeOutcome::REOPEN) {
                throw ValidationException::withMessages([
                    'outcome' => 'Tranh chấp bị từ chối chỉ được đưa giao dịch trở lại luồng xử lý.',
                ]);
            }

            if ($data['status'] === 'resolved' && $outcome === DisputeOutcome::REOPEN) {
                throw ValidationException::withMessages([
                    'outcome' => 'Tranh chấp đã chấp nhận phải kết thúc bằng hoàn tất hoặc hủy giao dịch.',
                ]);
            }

            $lockedDispute->update([
                'status' => $data['status'],
                'resolution' => $data['resolution'],
                'outcome' => $outcome->value,
                'resolved_at' => now(),
                'resolved_by' => $adminId,
            ]);

            $transaction->update([
                'status' => $next,
                'completed_at' => $next === 'completed' ? now() : null,
            ]);

            if ($outcome === DisputeOutcome::COMPLETE) {
                $this->settleCompleted($transaction);
                if ($transaction->product) {
                    $this->availability->transition(
                        $transaction->product,
                        $transaction->transaction_type === 'rental'
                            ? ProductAvailabilityStatus::RENTED
                            : ProductAvailabilityStatus::SOLD,
                        $transaction,
                        'Tranh chấp được giải quyết và giao dịch hoàn tất',
                    );
                }
            } elseif ($outcome === DisputeOutcome::CANCEL_REFUND) {
                $this->refundHeldPayments($transaction, 'dispute_cancel');
                $this->releaseProductAfterCancellation($transaction);
            } elseif ($outcome === DisputeOutcome::CANCEL_NO_REFUND) {
                $this->releaseProductAfterCancellation($transaction);
            }

            $this->event(
                $transaction,
                'dispute_'.$data['status'],
                'user',
                $adminId,
                'Đã xử lý tranh chấp',
                $data['resolution'],
                ['outcome' => $outcome->value, 'transaction_status' => $next],
            );
            $this->notifyDisputeOutcome($transaction, $lockedDispute, $outcome, $data['resolution']);

            return $lockedDispute->fresh(['transaction', 'openedBy:id,code,name']);
        });
    }


    private function notifyDisputeOutcome(Transaction $transaction, MarketplaceDispute $dispute, DisputeOutcome $outcome, string $resolution): void
    {
        $message = $outcome->label().'. Kết luận: '.$resolution;
        $payload = [
            'dispute_id' => $dispute->id,
            'dispute_code' => $dispute->code,
            'outcome' => $outcome->value,
            'transaction_status' => $transaction->status,
        ];

        foreach (array_unique([$transaction->buyer_customer_id, $transaction->seller_customer_id]) as $customerId) {
            $this->notifications->send(
                $customerId,
                'dispute_outcome',
                'Tranh chấp đã có kết quả',
                $message,
                '/account/purchases/'.$transaction->id,
                $payload + ['transaction_id' => $transaction->id, 'transaction_code' => $transaction->code],
            );
        }
    }

    public function event(Transaction $t, string $type, ?string $actorType, ?int $actorId, string $title, ?string $description = null, array $metadata = []): TransactionEvent
    {
        return TransactionEvent::create(['transaction_id' => $t->id, 'event_type' => $type, 'actor_type' => $actorType, 'actor_id' => $actorId, 'title' => $title, 'description' => $description, 'metadata' => $metadata]);
    }

    public function load(Transaction $t): Transaction
    {
        return $t->fresh(['product.rentalRates', 'buyer:id,code,name,avatar_url', 'seller:id,code,name,avatar_url', 'payments', 'events', 'disputes', 'checkpoints', 'documents', 'assetSnapshots']);
    }

    private function recordCheckoutIdempotencyAudit(string $event, Transaction $transaction, int $buyerId, string $key, string $requestHash): void
    {
        AuditLog::query()->create([
            'audit_type' => 'business_trail',
            'event_type' => $event,
            'risk_level' => $event === 'checkout_idempotency_conflict' ? 'high' : 'low',
            'actor_type' => 'customer',
            'actor_id' => $buyerId,
            'entity_type' => 'transaction',
            'entity_id' => (string) $transaction->id,
            'context_type' => 'product',
            'context_id' => (string) $transaction->product_id,
            'title' => $event === 'checkout_idempotency_conflict' ? 'Xung đột khóa checkout' : 'Checkout được phát lại an toàn',
            'description' => $event === 'checkout_idempotency_conflict'
                ? 'Cùng khóa chống trùng được gửi với payload khác.'
                : 'Yêu cầu checkout lặp lại đã trả về giao dịch hiện có.',
            'metadata' => [
                'idempotency_key' => $key,
                'request_hash' => $requestHash,
                'stored_request_hash' => $transaction->request_hash,
            ],
        ]);
    }

}
