<?php

namespace App\Services\Marketplace;

use App\Models\Customer;
use App\Models\EscrowBox;
use App\Models\EscrowBoxAgreementVersion;
use App\Models\EscrowBoxEvent;
use App\Models\EscrowBoxHandoverStep;
use App\Models\EscrowBoxPaymentObligation;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Support\Marketplace\MoneyMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EscrowBoxService
{
    public function __construct(
        private EscrowBoxFeeService $fees,
        private MarketplaceNotificationService $notifications,
        private TransactionSettlementService $settlements,
        private TransactionDisputeResolutionService $disputes,
    ) {}

    public function create(int $customerId, array $data): array
    {
        return DB::transaction(function () use ($customerId, $data) {
            Customer::query()->lockForUpdate()->findOrFail($customerId);
            $rawToken = Str::random(64);
            $terms = $this->terms($data);
            $box = EscrowBox::query()->create([
                'code' => 'BOX-'.strtoupper(Str::random(10)),
                'invite_token_hash' => hash('sha256', $rawToken),
                'invite_expires_at' => now()->addHours((int) ($data['expires_in_hours'] ?? 72)),
                'created_by_customer_id' => $customerId,
                'party_a_customer_id' => $customerId,
                'status' => 'awaiting_counterparty',
                'deal_type' => $data['deal_type'],
                'agreement_version' => 1,
                'agreement_terms' => $terms,
                'party_a_confirmed_at' => now(),
                'party_a_confirmed_version' => 1,
                'topup_payer_side' => $data['deal_type'] === 'exchange_with_topup' ? $data['topup_payer_side'] : null,
                'topup_amount' => $data['deal_type'] === 'exchange_with_topup' ? $data['topup_amount'] : '0.00',
                'fee_payer_mode' => $data['fee_payer_mode'],
                'inspection_period_minutes' => $data['inspection_period_minutes'],
                'expected_version' => 1,
                'expires_at' => now()->addDays(14),
            ]);
            EscrowBoxAgreementVersion::query()->create([
                'escrow_box_id' => $box->id,
                'version' => 1,
                'terms' => $terms,
                'changed_by_side' => 'party_a',
                'changed_by_customer_id' => $customerId,
                'change_note' => 'Khởi tạo box giao dịch trung gian.',
            ]);
            $fee = $this->fees->calculate($box);
            $this->applyFee($box, $fee);
            $this->event($box, 'box_created', 'customer', $customerId, 'party_a', ['agreement_version' => 1]);

            return [
                'box' => $box->fresh(),
                'invite_token' => $rawToken,
                'invite_path' => '/escrow-box/join/'.$rawToken,
            ];
        });
    }

    public function preview(string $rawToken): EscrowBox
    {
        $box = EscrowBox::query()->where('invite_token_hash', hash('sha256', $rawToken))->first();
        abort_unless($box && $box->status === 'awaiting_counterparty' && $box->invite_expires_at?->isFuture(), 404, 'Box giao dịch này không còn khả dụng.');

        return $box;
    }

    public function claim(string $rawToken, int $customerId): EscrowBox
    {
        return DB::transaction(function () use ($rawToken, $customerId) {
            $box = EscrowBox::query()->where('invite_token_hash', hash('sha256', $rawToken))->lockForUpdate()->first();
            abort_unless($box && $box->status === 'awaiting_counterparty' && $box->invite_expires_at?->isFuture(), 404, 'Box giao dịch này không còn khả dụng.');
            if ((int) $box->party_a_customer_id === $customerId) {
                throw ValidationException::withMessages(['invite' => 'Người tạo không thể tự nhận vai trò Bên B.']);
            }
            Customer::query()->lockForUpdate()->findOrFail($customerId);
            $box->update([
                'party_b_customer_id' => $customerId,
                'invite_claimed_at' => now(),
                'invite_token_hash' => null,
                'status' => 'terms_pending',
                'party_b_confirmed_at' => null,
                'party_b_confirmed_version' => null,
                'expected_version' => $box->expected_version + 1,
            ]);
            $this->event($box, 'counterparty_claimed', 'customer', $customerId, 'party_b');
            $this->notifications->send(
                $box->party_a_customer_id,
                'escrow_box_claimed',
                'Box đã có Bên B',
                'Một người dùng đã nhận lời tham gia box. Danh tính được hệ thống bảo vệ.',
                "/account/escrow-boxes/{$box->id}",
                ['escrow_box_id' => $box->id, 'escrow_box_code' => $box->code],
            );

            return $this->load($box);
        });
    }

    public function updateTerms(EscrowBox $box, int $customerId, array $data): EscrowBox
    {
        return DB::transaction(function () use ($box, $customerId, $data) {
            $locked = EscrowBox::query()->lockForUpdate()->findOrFail($box->id);
            $side = $this->side($locked, $customerId);
            abort_unless($side, 403);
            $this->guardVersion($locked, (int) $data['expected_version']);
            if (! in_array($locked->status, ['terms_pending', 'changes_requested'], true)) {
                throw ValidationException::withMessages(['status' => 'Box không còn cho phép sửa điều khoản.']);
            }
            $version = $locked->agreement_version + 1;
            $terms = $this->terms($data);
            $locked->update([
                'deal_type' => $data['deal_type'],
                'agreement_version' => $version,
                'agreement_terms' => $terms,
                'party_a_confirmed_at' => null,
                'party_b_confirmed_at' => null,
                'party_a_confirmed_version' => null,
                'party_b_confirmed_version' => null,
                'topup_payer_side' => $data['deal_type'] === 'exchange_with_topup' ? $data['topup_payer_side'] : null,
                'topup_amount' => $data['deal_type'] === 'exchange_with_topup' ? $data['topup_amount'] : '0.00',
                'fee_payer_mode' => $data['fee_payer_mode'],
                'inspection_period_minutes' => $data['inspection_period_minutes'],
                'status' => 'terms_pending',
                'admin_review_note' => null,
                'expected_version' => $locked->expected_version + 1,
            ]);
            EscrowBoxAgreementVersion::query()->create([
                'escrow_box_id' => $locked->id,
                'version' => $version,
                'terms' => $terms,
                'changed_by_side' => $side,
                'changed_by_customer_id' => $customerId,
                'change_note' => $data['change_note'] ?? null,
            ]);
            $this->applyFee($locked, $this->fees->calculate($locked));
            $this->event($locked, 'terms_updated', 'customer', $customerId, $side, ['agreement_version' => $version]);

            return $this->load($locked);
        });
    }

    public function confirm(EscrowBox $box, int $customerId, int $expectedVersion): EscrowBox
    {
        return DB::transaction(function () use ($box, $customerId, $expectedVersion) {
            $locked = EscrowBox::query()->lockForUpdate()->findOrFail($box->id);
            $side = $this->side($locked, $customerId);
            abort_unless($side, 403);
            $this->guardVersion($locked, $expectedVersion);
            if (! in_array($locked->status, ['terms_pending', 'changes_requested'], true)) {
                throw ValidationException::withMessages(['status' => 'Box không ở trạng thái xác nhận điều khoản.']);
            }
            $attributes = [
                $side.'_confirmed_at' => now(),
                $side.'_confirmed_version' => $locked->agreement_version,
                'expected_version' => $locked->expected_version + 1,
            ];
            $locked->update($attributes);
            $locked->refresh();
            if ($locked->party_a_confirmed_version === $locked->agreement_version && $locked->party_b_confirmed_version === $locked->agreement_version) {
                $locked->update(['status' => 'admin_review', 'expected_version' => $locked->expected_version + 1]);
                $this->event($locked, 'terms_confirmed', 'system', null, null, ['agreement_version' => $locked->agreement_version]);
            } else {
                $this->event($locked, 'party_confirmed', 'customer', $customerId, $side, ['agreement_version' => $locked->agreement_version]);
            }

            return $this->load($locked);
        });
    }

    public function cancel(EscrowBox $box, int $customerId, int $expectedVersion): EscrowBox
    {
        return DB::transaction(function () use ($box, $customerId, $expectedVersion) {
            $locked = EscrowBox::query()->lockForUpdate()->findOrFail($box->id);
            abort_unless($this->side($locked, $customerId), 403);
            $this->guardVersion($locked, $expectedVersion);
            if (! in_array($locked->status, ['awaiting_counterparty', 'terms_pending', 'changes_requested', 'admin_review'], true)) {
                throw ValidationException::withMessages(['status' => 'Box đã bước vào xử lý và không thể tự hủy.']);
            }
            $locked->update(['status' => 'cancelled', 'invite_token_hash' => null, 'expected_version' => $locked->expected_version + 1]);
            $this->event($locked, 'box_cancelled', 'customer', $customerId, $this->side($locked, $customerId));

            return $this->load($locked);
        });
    }

    public function adminReview(EscrowBox $box, int $adminId, array $data): EscrowBox
    {
        return DB::transaction(function () use ($box, $adminId, $data) {
            $locked = EscrowBox::query()->lockForUpdate()->findOrFail($box->id);
            $this->guardVersion($locked, (int) $data['expected_version']);
            if ($locked->status !== 'admin_review') {
                throw ValidationException::withMessages(['status' => 'Box chưa sẵn sàng để Admin duyệt.']);
            }
            if ($data['action'] === 'request_changes') {
                $locked->update(['status' => 'changes_requested', 'risk_level' => $data['risk_level'], 'admin_review_note' => $data['review_note'], 'reviewed_by' => $adminId, 'reviewed_at' => now(), 'expected_version' => $locked->expected_version + 1]);
                $this->event($locked, 'changes_requested', 'user', $adminId, null, ['note' => $data['review_note']]);

                return $this->load($locked);
            }
            if ($data['action'] === 'reject' || $data['risk_level'] === 'blocked') {
                $locked->update(['status' => 'rejected', 'risk_level' => $data['risk_level'], 'admin_review_note' => $data['review_note'], 'reviewed_by' => $adminId, 'reviewed_at' => now(), 'expected_version' => $locked->expected_version + 1]);
                $this->event($locked, 'admin_rejected', 'user', $adminId, null, ['note' => $data['review_note']]);

                return $this->load($locked);
            }

            $fee = $this->fees->calculate($locked, $data);
            $this->applyFee($locked, $fee, $data['fee_override_reason'] ?? null, $adminId);
            $transaction = $this->createFinancialAdapter($locked);
            $locked->update([
                'status' => 'payment_pending',
                'risk_level' => $data['risk_level'],
                'admin_review_note' => $data['review_note'] ?? null,
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
                'handover_sequence' => $data['handover_sequence'],
                'transaction_id' => $transaction->id,
                'expected_version' => $locked->expected_version + 1,
            ]);
            $this->createObligations($locked, $transaction);
            $this->createHandoverSteps($locked);
            $this->event($locked, 'admin_approved', 'user', $adminId, null, ['transaction_id' => $transaction->id, 'fee_snapshot' => $locked->fee_snapshot]);

            return $this->load($locked);
        });
    }

    public function submitHandover(EscrowBox $box, int $customerId, string $partySide, int $expectedVersion, string $note): EscrowBox
    {
        return DB::transaction(function () use ($box, $customerId, $partySide, $expectedVersion, $note) {
            $locked = EscrowBox::query()->lockForUpdate()->findOrFail($box->id);
            abort_unless($this->side($locked, $customerId) === $partySide, 403);
            $this->guardVersion($locked, $expectedVersion);
            if (! in_array($locked->status, ['handover_in_progress', 'payment_complete'], true)) {
                throw ValidationException::withMessages(['status' => 'Box chưa mở bước bàn giao.']);
            }
            $step = EscrowBoxHandoverStep::query()->where('escrow_box_id', $locked->id)->where('party_side', $partySide)->where('step_type', 'asset_handover')->lockForUpdate()->firstOrFail();
            if (! in_array($step->status, ['ready', 'changes_requested'], true)) {
                throw ValidationException::withMessages(['step' => 'Bước bàn giao chưa sẵn sàng.']);
            }
            if (! $locked->media()->where('party_side', $partySide)->exists()) {
                throw ValidationException::withMessages(['images' => 'Cần ít nhất một ảnh bằng chứng trước khi gửi bàn giao.']);
            }
            $step->update(['status' => 'submitted', 'customer_note' => $note, 'submitted_by_customer_id' => $customerId, 'submitted_at' => now(), 'expected_version' => $step->expected_version + 1]);
            $locked->update(['status' => 'handover_in_progress', 'expected_version' => $locked->expected_version + 1]);
            $this->event($locked, 'handover_submitted', 'customer', $customerId, $partySide, ['step_id' => $step->id]);

            return $this->load($locked);
        });
    }

    public function reviewHandover(EscrowBox $box, EscrowBoxHandoverStep $step, int $adminId, array $data): EscrowBox
    {
        return DB::transaction(function () use ($box, $step, $adminId, $data) {
            $locked = EscrowBox::query()->lockForUpdate()->findOrFail($box->id);
            $lockedStep = EscrowBoxHandoverStep::query()->where('escrow_box_id', $locked->id)->lockForUpdate()->findOrFail($step->id);
            if ($lockedStep->expected_version !== (int) $data['expected_version']) {
                throw ValidationException::withMessages(['expected_version' => 'Bước bàn giao đã thay đổi.']);
            }
            if ($lockedStep->status !== 'submitted') {
                throw ValidationException::withMessages(['step' => 'Bước chưa được gửi để xác minh.']);
            }
            $status = match ($data['action']) {
                'verify' => 'verified', 'request_more' => 'changes_requested', default => 'rejected'
            };
            $lockedStep->update(['status' => $status, 'admin_note' => $data['note'] ?? null, 'verified_by' => $data['action'] === 'verify' ? $adminId : null, 'verified_at' => $data['action'] === 'verify' ? now() : null, 'expected_version' => $lockedStep->expected_version + 1]);
            $this->event($locked, 'handover_'.$status, 'user', $adminId, $lockedStep->party_side, ['step_id' => $lockedStep->id]);
            if ($status === 'verified') {
                $this->advanceHandover($locked);
            }

            return $this->load($locked);
        });
    }

    public function confirmReceipt(EscrowBox $box, int $customerId, int $expectedVersion): EscrowBox
    {
        return DB::transaction(function () use ($box, $customerId, $expectedVersion) {
            $locked = EscrowBox::query()->lockForUpdate()->findOrFail($box->id);
            $side = $this->side($locked, $customerId);
            abort_unless($side, 403);
            $this->guardVersion($locked, $expectedVersion);
            if ($locked->status !== 'inspection') {
                throw ValidationException::withMessages(['status' => 'Box chưa ở giai đoạn xác nhận nhận tài sản.']);
            }
            $locked->update([
                $side.'_received_at' => now(),
                'expected_version' => $locked->expected_version + 1,
            ]);
            $this->event($locked, 'receipt_confirmed', 'customer', $customerId, $side);
            $locked->refresh();
            if ($locked->party_a_received_at && $locked->party_b_received_at) {
                $transaction = Transaction::query()->lockForUpdate()->findOrFail($locked->transaction_id);
                $transaction->update(['status' => 'completed', 'completed_at' => now()]);
                $this->settlements->settleCompleted($transaction);
                $locked->update(['status' => 'settled', 'expected_version' => $locked->expected_version + 1]);
                $this->event($locked, 'box_settled', 'system', null, null, ['transaction_id' => $transaction->id]);
            }

            return $this->load($locked);
        });
    }

    public function openDispute(EscrowBox $box, int $customerId, int $expectedVersion, array $data): EscrowBox
    {
        return DB::transaction(function () use ($box, $customerId, $expectedVersion, $data) {
            $locked = EscrowBox::query()->lockForUpdate()->findOrFail($box->id);
            abort_unless($this->side($locked, $customerId), 403);
            $this->guardVersion($locked, $expectedVersion);
            if (! in_array($locked->status, ['handover_in_progress', 'inspection'], true) || ! $locked->transaction_id) {
                throw ValidationException::withMessages(['status' => 'Box chưa thể mở tranh chấp.']);
            }
            $transaction = Transaction::query()->findOrFail($locked->transaction_id);
            $this->disputes->open($transaction, $customerId, $data);
            $locked->update(['status' => 'disputed', 'expected_version' => $locked->expected_version + 1]);
            $locked->media()->update(['retention_locked_until' => now()->addYears(2)]);
            $this->event($locked, 'dispute_opened', 'customer', $customerId, $this->side($locked, $customerId), ['reason' => $data['reason']]);

            return $this->load($locked);
        });
    }

    private function createFinancialAdapter(EscrowBox $box): Transaction
    {
        $terms = $box->agreement_terms;
        $payerSide = $box->topup_payer_side ?: ($box->fee_payer_mode === 'party_a' ? 'party_a' : 'party_b');
        $buyerId = $payerSide === 'party_a' ? $box->party_a_customer_id : $box->party_b_customer_id;
        $sellerId = $payerSide === 'party_a' ? $box->party_b_customer_id : $box->party_a_customer_id;
        $product = Product::query()->create([
            'code' => 'BOX-ASSET-'.strtoupper(Str::random(8)),
            'name' => 'Tài sản box '.$box->code,
            'product_type' => 'other',
            'delivery_method' => 'admin_observed',
            'inspection_period_minutes' => $box->inspection_period_minutes,
            'requires_pre_handover_snapshot' => true,
            'status' => 'active',
            'approval_status' => 'approved',
            'is_published' => false,
            'sale_price' => $box->topup_amount,
            'availability_status' => 'available',
            'description' => 'Adapter tài chính nội bộ cho '.$box->code,
            'attributes' => ['escrow_box_id' => $box->id, 'private_escrow_box' => true],
            'owner_customer_id' => $sellerId,
        ]);
        $total = MoneyMath::add($box->topup_amount, $box->final_fee);

        return Transaction::query()->create([
            'code' => 'TRX-BOX-'.strtoupper(Str::random(8)),
            'transaction_type' => 'purchase',
            'purchase_mode' => 'full',
            'initiation_source' => 'escrow_box',
            'agreement_status' => 'accepted',
            'initiated_by_customer_id' => $box->created_by_customer_id,
            'agreement_version' => $box->agreement_version,
            'agreement_terms' => $terms,
            'buyer_accepted_at' => $box->party_a_confirmed_at,
            'seller_accepted_at' => $box->party_b_confirmed_at,
            'asset_delivery_method' => 'admin_observed',
            'inspection_period_minutes' => $box->inspection_period_minutes,
            'requires_pre_handover_snapshot' => true,
            'product_id' => $product->id,
            'buyer_customer_id' => $buyerId,
            'seller_customer_id' => $sellerId,
            'transaction_value' => $box->topup_amount,
            'service_fee' => $box->final_fee,
            'buyer_fee_amount' => $box->final_fee,
            'seller_fee_amount' => '0.00',
            'tax_amount' => '0.00',
            'seller_net_amount' => $box->topup_amount,
            'fee_policy_version' => 'escrow-box:'.($box->fee_rule_version ?? 1),
            'fee_snapshot' => $box->fee_snapshot,
            'total_payable' => $total,
            'initial_payment_amount' => $total,
            'transaction_date' => now()->toDateString(),
            'status' => MoneyMath::compare($total, '0.00') > 0 ? 'pending_payment' : 'paid',
            'note' => 'Financial adapter for '.$box->code,
        ]);
    }

    private function createObligations(EscrowBox $box, Transaction $transaction): void
    {
        $obligations = [];
        if (MoneyMath::compare($box->topup_amount, '0.00') > 0) {
            $obligations[] = [$box->topup_payer_side, 'topup', $box->topup_amount, true];
        }
        if (MoneyMath::compare($box->party_a_fee_amount, '0.00') > 0) {
            $obligations[] = ['party_a', 'platform_fee', $box->party_a_fee_amount, false];
        }
        if (MoneyMath::compare($box->party_b_fee_amount, '0.00') > 0) {
            $obligations[] = ['party_b', 'platform_fee', $box->party_b_fee_amount, false];
        }
        foreach ($obligations as $index => [$side, $type, $amount, $refundable]) {
            $customerId = $side === 'party_a' ? $box->party_a_customer_id : $box->party_b_customer_id;
            $payment = TransactionPayment::query()->create([
                'code' => 'PAY-BOX-'.strtoupper(Str::random(8)),
                'transaction_id' => $transaction->id,
                'customer_id' => $customerId,
                'payment_type' => 'escrow_box_'.$type.'_'.$side,
                'component_type' => $type === 'topup' ? 'principal' : 'platform_fee',
                'installment_number' => null,
                'cycle_number' => $index + 1,
                'amount' => $amount,
                'refundable' => $refundable,
                'status' => 'pending',
                'settlement_status' => 'unsettled',
                'due_date' => now()->toDateString(),
            ]);
            EscrowBoxPaymentObligation::query()->create(['escrow_box_id' => $box->id, 'party_side' => $side, 'type' => $type, 'amount' => $amount, 'status' => 'pending', 'transaction_payment_id' => $payment->id]);
        }
        if ($obligations === []) {
            $box->update(['status' => 'payment_complete']);
        }
    }

    private function createHandoverSteps(EscrowBox $box): void
    {
        $order = match ($box->handover_sequence) {
            'party_b_first' => ['party_b', 'party_a'],
            default => ['party_a', 'party_b'],
        };
        foreach ($order as $index => $side) {
            $status = $box->handover_sequence === 'simultaneous_admin_observed' || $index === 0 ? 'ready' : 'blocked';
            EscrowBoxHandoverStep::query()->create(['escrow_box_id' => $box->id, 'party_side' => $side, 'step_type' => 'asset_handover', 'status' => $status, 'sequence_no' => $index + 1, 'expected_version' => 1]);
        }
    }

    private function advanceHandover(EscrowBox $box): void
    {
        $steps = EscrowBoxHandoverStep::query()->where('escrow_box_id', $box->id)->orderBy('sequence_no')->lockForUpdate()->get();
        $next = $steps->first(fn ($step) => $step->status === 'blocked');
        if ($next) {
            $previousVerified = $steps->where('sequence_no', '<', $next->sequence_no)->every(fn ($step) => $step->status === 'verified');
            if ($previousVerified) {
                $next->update(['status' => 'ready', 'expected_version' => $next->expected_version + 1]);
            }
            $box->update(['status' => 'handover_in_progress', 'expected_version' => $box->expected_version + 1]);

            return;
        }
        if ($steps->every(fn ($step) => $step->status === 'verified')) {
            $box->update(['status' => 'inspection', 'inspection_started_at' => now(), 'inspection_deadline_at' => now()->addMinutes($box->inspection_period_minutes), 'expected_version' => $box->expected_version + 1]);
        }
    }

    private function terms(array $data): array
    {
        return [
            'party_a_asset' => $data['party_a_asset'],
            'party_b_asset' => $data['party_b_asset'],
            'success_conditions' => $data['success_conditions'],
            'cancellation_conditions' => $data['cancellation_conditions'] ?? null,
            'additional_terms' => $data['additional_terms'] ?? null,
            'privacy_notice' => 'Hai bên chỉ được hiển thị dưới bí danh Bên A/B. Không chia sẻ email, số điện thoại, username hoặc liên kết liên hệ.',
        ];
    }

    private function applyFee(EscrowBox $box, array $fee, ?string $reason = null, ?int $adminId = null): void
    {
        $box->update([
            'fee_payer_mode' => $fee['fee_payer_mode'], 'party_a_fee_amount' => $fee['party_a_fee_amount'], 'party_b_fee_amount' => $fee['party_b_fee_amount'],
            'calculated_fee' => $fee['calculated_fee'], 'final_fee' => $fee['final_fee'], 'fee_rule_id' => $fee['rule']?->id,
            'fee_rule_version' => $fee['rule']?->version ?? 1, 'fee_snapshot' => $fee['snapshot'], 'fee_override_reason' => $reason,
            'fee_overridden_by' => $reason ? $adminId : null, 'fee_overridden_at' => $reason ? now() : null,
        ]);
    }

    private function load(EscrowBox $box): EscrowBox
    {
        return $box->fresh(['partyA', 'partyB', 'obligations', 'handoverSteps.media', 'events' => fn ($query) => $query->latest('occurred_at')->limit(100), 'transaction.payments']);
    }

    private function side(EscrowBox $box, int $customerId): ?string
    {
        if ((int) $box->party_a_customer_id === $customerId) {
            return 'party_a';
        }
        if ((int) $box->party_b_customer_id === $customerId) {
            return 'party_b';
        }

        return null;
    }

    private function guardVersion(EscrowBox $box, int $expectedVersion): void
    {
        if ($box->expected_version !== $expectedVersion) {
            throw ValidationException::withMessages(['expected_version' => 'Box đã thay đổi. Vui lòng tải lại dữ liệu mới nhất.']);
        }
    }

    private function event(EscrowBox $box, string $type, string $actorType, ?int $actorId, ?string $actorSide, array $metadata = []): void
    {
        EscrowBoxEvent::query()->create(['escrow_box_id' => $box->id, 'event_type' => $type, 'actor_type' => $actorType, 'actor_id' => $actorId, 'actor_side' => $actorSide, 'metadata' => $metadata, 'occurred_at' => now()]);
    }
}
