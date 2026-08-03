<?php

namespace App\Services\Marketplace;

use App\Enums\ProductAvailabilityStatus;
use App\Enums\ProductSelectionContext;
use App\Models\AuditLog;
use App\Models\MarketplaceDispute;
use App\Models\MarketplaceNotification;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionCheckpoint;
use App\Models\TransactionEvent;
use App\Models\TransactionPayment;
use App\Services\ProductAvailabilityService;
use App\Services\ProductSelectionService;
use App\Support\Marketplace\MoneyMath;
use App\Support\Marketplace\TransactionIdempotency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TransactionLifecycleService
{
    public function __construct(private MarketplaceNotificationService $notifications, private MarketplaceFeeCalculator $fees, private ProductAvailabilityService $availability, private ProductSelectionService $selection, private TransactionPaymentPlanService $paymentPlans, private TransactionPaymentCaptureService $paymentCapture, private TransactionSettlementService $settlements, private TransactionActionPolicy $actionPolicy, private TransactionDisputeResolutionService $disputes) {}

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
            [$value,$deposit,$rentalMeta] = $isRental ? $this->paymentPlans->resolveRentalPricing($locked, $data) : [(string) $locked->sale_price, (string) ($locked->sale_deposit_amount ?? 0), []];
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
            $this->paymentPlans->createPaymentPlan($transaction, $rentalMeta);
            $this->event($transaction, 'created', 'customer', $buyerId, 'Đã tạo giao dịch', 'Giao dịch đã được tạo và đang chờ thanh toán. Sản phẩm chỉ được giữ chỗ khi thanh toán được xác nhận.');
            $this->notifications->transaction($transaction->seller_customer_id, 'transaction_created', 'Có giao dịch mới', 'Một khách hàng đã tạo giao dịch từ sản phẩm '.$locked->code.'.', $transaction->id, $transaction->code);
            $this->notifications->transaction($transaction->buyer_customer_id, 'transaction_created', 'Đã tạo giao dịch', 'Giao dịch '.$transaction->code.' đã được tạo và đang chờ thanh toán.', $transaction->id, $transaction->code);

            return $this->load($transaction);
        });
    }

    public function submitPayment(TransactionPayment $payment, int $customerId, array $data): TransactionPayment
    {
        return $this->paymentCapture->submit($payment, $customerId, $data);
    }

    public function confirmPayment(TransactionPayment $payment, int $adminId): TransactionPayment
    {
        return $this->paymentCapture->confirm($payment, $adminId);
    }

    public function allowedActions(Transaction $t, int $customerId): array
    {
        return $this->actionPolicy->allowedCustomerActions($t, $customerId);
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
                $this->settlements->settleCompleted($t);
                $t->product && $this->availability->transition($t->product, $t->transaction_type === 'rental' ? ProductAvailabilityStatus::AVAILABLE : ProductAvailabilityStatus::SOLD, $t, 'Giao dịch hoàn tất');
            }if ($next === 'cancelled') {
                $this->settlements->releaseProductAfterCancellation($t);
            }$this->event($t, $action, $actorType, $actorId, $title, null, ['checkpoint' => $checkpoint]);

            return $this->load($t);
        });
    }

    public function allowedAdminActions(Transaction $transaction): array
    {
        return $this->actionPolicy->allowedAdminActions($transaction);
    }

    public function adminTransition(Transaction $transaction, string $action, int $adminId, ?string $note = null, ?string $rentalDepositDeduction = null, ?string $deductionNote = null): Transaction
    {
        return DB::transaction(function () use ($transaction, $action, $adminId, $note, $rentalDepositDeduction, $deductionNote) {
            $t = Transaction::lockForUpdate()->findOrFail($transaction->id);
            if (! in_array($action, $this->allowedAdminActions($t), true)) {
                throw ValidationException::withMessages(['action' => 'Hành động không còn hợp lệ ở trạng thái hiện tại.']);
            }
            $map = ['force_handover' => $t->transaction_type === 'rental' ? 'active' : 'handed_over', 'force_return' => 'returned', 'complete' => 'completed', 'cancel' => 'cancelled', 'reopen' => 'pending_payment'];
            if (! isset($map[$action])) {
                throw ValidationException::withMessages(['action' => 'Hành động quản trị không hợp lệ.']);
            }
            $deduction = $rentalDepositDeduction ?? '0.00';
            if ($action !== 'complete' && bccomp($deduction, '0.00', 2) > 0) {
                throw ValidationException::withMessages(['rental_deposit_deduction_amount' => 'Chỉ được khấu trừ cọc khi hoàn tất giao dịch thuê.']);
            }
            if ($action === 'complete' && bccomp($deduction, '0.00', 2) > 0 && trim((string) $deductionNote) === '') {
                throw ValidationException::withMessages(['rental_deposit_deduction_note' => 'Vui lòng nhập lý do khấu trừ tiền cọc.']);
            }
            if ($action === 'complete' && $t->transaction_type === 'rental' && bccomp($deduction, (string) $t->deposit_amount, 2) > 0) {
                throw ValidationException::withMessages(['rental_deposit_deduction_amount' => 'Số tiền khấu trừ không được vượt quá tiền cọc.']);
            }
            if ($action === 'complete' && $t->transaction_type !== 'rental' && bccomp($deduction, '0.00', 2) > 0) {
                throw ValidationException::withMessages(['rental_deposit_deduction_amount' => 'Chỉ giao dịch thuê mới được khấu trừ cọc.']);
            }

            $updates = ['status' => $map[$action]];
            if ($action === 'complete' && $t->transaction_type === 'rental') {
                $updates['rental_deposit_deduction_amount'] = $deduction;
                $updates['rental_deposit_deduction_note'] = bccomp($deduction, '0.00', 2) > 0 ? $deductionNote : null;
            }
            if ($map[$action] === 'completed') {
                $updates['completed_at'] = now();
            } elseif ($action === 'reopen') {
                $updates['completed_at'] = null;
            }
            $t->update($updates);
            if ($map[$action] === 'completed') {
                $this->settlements->settleCompleted($t, $deduction, $deductionNote);
                if ($t->product) {
                    $this->availability->transition($t->product, $t->transaction_type === 'rental' ? ProductAvailabilityStatus::AVAILABLE : ProductAvailabilityStatus::SOLD, $t, 'Quản trị viên hoàn tất giao dịch');
                }
            }if ($map[$action] === 'cancelled') {
                $this->settlements->refundHeldPayments($t, 'admin_cancel');
                $this->settlements->releaseProductAfterCancellation($t);
            }$this->event(
                $t,
                'admin_'.$action,
                'user',
                $adminId,
                'Quản trị viên cập nhật giao dịch',
                $note,
                [
                    'rental_deposit_deduction_amount' => $deduction,
                    'rental_deposit_deduction_note' => $deductionNote,
                ],
            );

            $this->resolveNotificationsForTransaction($t, $adminId, 'Đã xử lý bằng action '.$action);

            return $this->load($t);
        });
    }

    private function resolveNotificationsForTransaction(Transaction $transaction, int $adminId, string $note): void
    {
        MarketplaceNotification::query()
            ->where('transaction_id', $transaction->id)
            ->whereNull('handled_at')
            ->update([
                'handled_at' => now(),
                'handled_by' => $adminId,
                'handling_note' => $note,
                'read_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function openDispute(Transaction $t, int $customerId, array $data): MarketplaceDispute
    {
        return $this->disputes->open($t, $customerId, $data);
    }

    public function resolveDispute(MarketplaceDispute $d, int $adminId, array $data): MarketplaceDispute
    {
        return $this->disputes->resolve($d, $adminId, $data);
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
