<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('customer_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('unverified')->index();
            $table->string('document_type', 40)->nullable();
            $table->string('document_number', 80)->nullable();
            $table->string('document_front_url')->nullable();
            $table->string('document_back_url')->nullable();
            $table->string('selfie_url')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamps();
        });
        Schema::create('customer_payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('bank_code', 30);
            $table->string('bank_name', 120);
            $table->string('account_name', 150);
            $table->string('account_number', 80);
            $table->string('status', 30)->default('pending')->index();
            $table->boolean('is_default')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->unique(['customer_id','bank_code','account_number'],'customer_payout_account_unique');
        });
        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('idempotency_key', 150)->nullable()->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('payout_account_id')->constrained('customer_payout_accounts')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->decimal('fee_amount', 18, 2)->default(0);
            $table->decimal('net_amount', 18, 2);
            $table->string('status', 30)->default('submitted')->index();
            $table->foreignId('reservation_wallet_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->string('payment_reference', 150)->nullable();
            $table->string('proof_url')->nullable();
            $table->text('customer_note')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['customer_id','status']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('withdrawal_requests');
        Schema::dropIfExists('customer_payout_accounts');
        Schema::dropIfExists('customer_verifications');
    }
};
