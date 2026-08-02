<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('available_balance', 18, 2)->default(0);
            $table->decimal('held_balance', 18, 2)->default(0);
            $table->decimal('lifetime_credit', 18, 2)->default(0);
            $table->decimal('lifetime_debit', 18, 2)->default(0);
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();
        });
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('idempotency_key', 150)->nullable()->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('transaction_payment_id')->nullable()->index();
            $table->string('type', 40)->index();
            $table->string('direction', 10)->index();
            $table->string('balance_bucket', 20)->default('available')->index();
            $table->decimal('amount', 18, 2);
            $table->decimal('available_before', 18, 2)->default(0);
            $table->decimal('available_after', 18, 2)->default(0);
            $table->decimal('held_before', 18, 2)->default(0);
            $table->decimal('held_after', 18, 2)->default(0);
            $table->decimal('balance_after', 18, 2)->default(0);
            $table->string('status', 20)->default('pending')->index();
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('payment_method', 50)->nullable();
            $table->string('external_reference', 150)->nullable();
            $table->string('proof_image_url')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->text('review_note')->nullable();
            $table->json('metadata')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['customer_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['transaction_id', 'type']);
        });
        Schema::create('transaction_payments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('payment_type', 30)->index();
            $table->string('component_type', 30)->default('principal')->index();
            $table->unsignedTinyInteger('installment_number')->nullable();
            $table->unsignedSmallInteger('cycle_number')->nullable();
            $table->decimal('amount', 18, 2);
            $table->boolean('refundable')->default(false);
            $table->string('payment_method', 50)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->string('settlement_status', 20)->default('unsettled')->index();
            $table->string('reference', 150)->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('wallet_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['transaction_id', 'payment_type', 'installment_number', 'cycle_number'], 'transaction_payment_plan_unique');
            $table->index(['transaction_id', 'status', 'due_date']);
        });
        Schema::create('transaction_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 50)->index();
            $table->string('actor_type', 30)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
        Schema::create('marketplace_disputes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->foreignId('transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('opened_by_customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('reason', 50)->index();
            $table->string('status', 30)->default('open')->index();
            $table->text('description');
            $table->json('evidence')->nullable();
            $table->text('resolution')->nullable();
            $table->string('outcome', 30)->nullable()->index();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_disputes');
        Schema::dropIfExists('transaction_events');
        Schema::dropIfExists('transaction_payments');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('customer_wallets');
    }
};
