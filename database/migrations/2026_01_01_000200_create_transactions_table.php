<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('idempotency_key', 150)->nullable()->unique();
            $table->char('request_hash', 64)->nullable();
            $table->string('transaction_type', 20)->index();
            $table->string('purchase_mode', 20)->default('full')->index();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('buyer_customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('seller_customer_id')->constrained('customers')->restrictOnDelete();
            $table->decimal('transaction_value', 18, 2);
            $table->decimal('service_fee', 18, 2)->default(0);
            $table->decimal('discount', 18, 2)->default(0);
            $table->decimal('deposit_amount', 18, 2)->default(0);
            $table->decimal('initial_payment_amount', 18, 2)->default(0);
            $table->unsignedTinyInteger('installment_count')->nullable();
            $table->string('installment_interval_unit', 20)->nullable();
            $table->unsignedInteger('installment_interval_count')->nullable();
            $table->string('rental_period_unit', 20)->nullable();
            $table->unsignedInteger('rental_period_count')->nullable();
            $table->string('rental_billing_mode', 20)->nullable();
            $table->string('rental_billing_cycle_unit', 20)->nullable();
            $table->unsignedInteger('rental_billing_cycle_count')->nullable();
            $table->decimal('total_payable', 18, 2);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('refunded_amount', 18, 2)->default(0);
            $table->decimal('escrow_amount', 18, 2)->default(0);
            $table->decimal('released_amount', 18, 2)->default(0);
            $table->decimal('wallet_paid_amount', 18, 2)->default(0);
            $table->date('transaction_date');
            $table->date('due_date')->nullable();
            $table->date('next_payment_due_at')->nullable();
            $table->timestamp('rental_start_at')->nullable();
            $table->timestamp('rental_end_at')->nullable();
            $table->timestamp('handed_over_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->string('payment_method', 50)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['buyer_customer_id', 'status']);
            $table->index(['seller_customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
