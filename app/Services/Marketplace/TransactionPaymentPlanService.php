<?php

namespace App\Services\Marketplace;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use Carbon\Carbon;
use Illuminate\Support\Str;

class TransactionPaymentPlanService
{
    public function resolveRentalPricing(Product $product, array $data): array
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

    public function createPaymentPlan(Transaction $t, array $rentalMeta = []): void
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

    private function syncNextDue(Transaction $t): void
    {
        $next = $t->payments()
            ->where('status', 'pending')
            ->orderBy('due_date')
            ->value('due_date');

        $t->update(['next_payment_due_at' => $next]);
    }
}
