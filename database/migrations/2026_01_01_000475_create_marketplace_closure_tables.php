<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_fee_policies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('transaction_type', 20)->nullable()->index();
            $table->decimal('buyer_fee_rate', 8, 4)->default(0);
            $table->decimal('buyer_fixed_fee', 18, 2)->default(0);
            $table->decimal('seller_fee_rate', 8, 4)->default(0);
            $table->decimal('seller_fixed_fee', 18, 2)->default(0);
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('effective_from')->nullable()->index();
            $table->timestamp('effective_to')->nullable()->index();
            $table->json('conditions')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('buyer_fee_amount', 18, 2)->default(0)->after('service_fee');
            $table->decimal('seller_fee_amount', 18, 2)->default(0)->after('buyer_fee_amount');
            $table->decimal('tax_amount', 18, 2)->default(0)->after('seller_fee_amount');
            $table->decimal('seller_net_amount', 18, 2)->default(0)->after('tax_amount');
            $table->string('fee_policy_version', 80)->nullable()->after('seller_net_amount');
            $table->json('fee_snapshot')->nullable()->after('fee_policy_version');
        });
        Schema::table('marketplace_disputes', function (Blueprint $table) {
            $table->string('case_type', 40)->default('dispute')->index()->after('opened_by_customer_id');
            $table->string('priority', 20)->default('normal')->index()->after('status');
            $table->foreignId('assigned_to')->nullable()->after('resolved_by')->constrained('users')->nullOnDelete();
            $table->timestamp('due_at')->nullable()->index()->after('assigned_to');
            $table->timestamp('last_message_at')->nullable()->index()->after('due_at');
        });
        Schema::create('marketplace_case_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('marketplace_disputes')->cascadeOnDelete();
            $table->string('actor_type', 20);
            $table->unsignedBigInteger('actor_id');
            $table->text('message');
            $table->json('attachments')->nullable();
            $table->boolean('is_internal')->default(false);
            $table->timestamps();
            $table->index(['case_id', 'created_at']);
        });
        Schema::create('marketplace_platform_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('idempotency_key', 150)->unique();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40)->index();
            $table->decimal('amount', 18, 2);
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
        Schema::create('transaction_asset_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 30)->index();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_type', 20)->default('customer');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('images')->nullable();
            $table->json('attributes')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();
            $table->unique(['transaction_id', 'stage', 'actor_type', 'actor_id'], 'asset_snapshot_actor_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_asset_snapshots');
        Schema::dropIfExists('marketplace_platform_ledger_entries');
        Schema::dropIfExists('marketplace_case_messages');
        Schema::table('marketplace_disputes', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropColumn(['case_type', 'priority', 'assigned_to', 'due_at', 'last_message_at']);
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['buyer_fee_amount', 'seller_fee_amount', 'tax_amount', 'seller_net_amount', 'fee_policy_version', 'fee_snapshot']);
        });
        Schema::dropIfExists('marketplace_fee_policies');
    }
};
