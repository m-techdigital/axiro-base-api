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
        Schema::create('marketplace_export_requests', function (Blueprint $table) {
            $table->id();
            $table->string('type', 60)->index();
            $table->string('status', 20)->default('pending')->index();
            $table->json('filters')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->text('error_message')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('escrow_fee_rules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->decimal('minimum_money_amount', 18, 2)->default(0);
            $table->decimal('maximum_money_amount', 18, 2)->nullable();
            $table->decimal('base_fee', 18, 2)->default(50000);
            $table->decimal('percentage_rate', 8, 4)->default(10);
            $table->decimal('minimum_fee', 18, 2)->default(50000);
            $table->decimal('maximum_fee', 18, 2)->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('effective_from')->nullable()->index();
            $table->timestamp('effective_to')->nullable()->index();
            $table->json('conditions')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('escrow_boxes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->char('invite_token_hash', 64)->nullable()->unique();
            $table->timestamp('invite_expires_at')->nullable()->index();
            $table->timestamp('invite_claimed_at')->nullable();
            $table->unsignedInteger('invite_generation')->default(1);
            $table->foreignId('created_by_customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('party_a_customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('party_b_customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->string('status', 40)->default('awaiting_counterparty')->index();
            $table->string('deal_type', 30)->default('exchange')->index();
            $table->unsignedInteger('agreement_version')->default(1);
            $table->json('agreement_terms');
            $table->timestamp('party_a_confirmed_at')->nullable();
            $table->timestamp('party_b_confirmed_at')->nullable();
            $table->unsignedInteger('party_a_confirmed_version')->nullable();
            $table->unsignedInteger('party_b_confirmed_version')->nullable();
            $table->string('topup_payer_side', 10)->nullable();
            $table->decimal('topup_amount', 18, 2)->default(0);
            $table->string('fee_payer_mode', 30)->default('party_b')->index();
            $table->decimal('party_a_fee_amount', 18, 2)->default(0);
            $table->decimal('party_b_fee_amount', 18, 2)->default(0);
            $table->decimal('calculated_fee', 18, 2)->default(0);
            $table->decimal('final_fee', 18, 2)->default(0);
            $table->foreignId('fee_rule_id')->nullable()->constrained('escrow_fee_rules')->nullOnDelete();
            $table->unsignedInteger('fee_rule_version')->nullable();
            $table->json('fee_snapshot')->nullable();
            $table->text('fee_override_reason')->nullable();
            $table->foreignId('fee_overridden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fee_overridden_at')->nullable();
            $table->string('risk_level', 20)->nullable()->index();
            $table->text('admin_review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('handover_sequence', 40)->nullable();
            $table->unsignedSmallInteger('inspection_period_minutes')->default(60);
            $table->timestamp('inspection_started_at')->nullable();
            $table->timestamp('inspection_deadline_at')->nullable()->index();
            $table->timestamp('party_a_received_at')->nullable();
            $table->timestamp('party_b_received_at')->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('expected_version')->default(1);
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['party_a_customer_id', 'status']);
            $table->index(['party_b_customer_id', 'status']);
        });

        Schema::create('escrow_box_agreement_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escrow_box_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('terms');
            $table->string('changed_by_side', 10);
            $table->foreignId('changed_by_customer_id')->constrained('customers')->restrictOnDelete();
            $table->text('change_note')->nullable();
            $table->timestamps();
            $table->unique(['escrow_box_id', 'version']);
        });

        Schema::create('escrow_box_payment_obligations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escrow_box_id')->constrained()->cascadeOnDelete();
            $table->string('party_side', 10)->index();
            $table->string('type', 30)->index();
            $table->decimal('amount', 18, 2);
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('transaction_payment_id')->nullable()->constrained('transaction_payments')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['escrow_box_id', 'party_side', 'type']);
        });

        Schema::create('escrow_box_handover_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escrow_box_id')->constrained()->cascadeOnDelete();
            $table->string('party_side', 10)->index();
            $table->string('step_type', 40)->index();
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedInteger('sequence_no');
            $table->text('customer_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->foreignId('submitted_by_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedInteger('expected_version')->default(1);
            $table->timestamps();
            $table->unique(['escrow_box_id', 'party_side', 'step_type']);
        });

        Schema::create('escrow_box_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escrow_box_id')->constrained()->cascadeOnDelete();
            $table->foreignId('handover_step_id')->nullable()->constrained('escrow_box_handover_steps')->cascadeOnDelete();
            $table->string('party_side', 10)->index();
            $table->foreignId('uploaded_by_customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('disk', 30)->default('local');
            $table->string('path');
            $table->string('thumbnail_path')->nullable();
            $table->string('mime', 80);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->char('checksum', 64)->index();
            $table->string('status', 20)->default('ready')->index();
            $table->timestamp('retention_locked_until')->nullable();
            $table->timestamps();
            $table->unique(['escrow_box_id', 'checksum']);
        });

        Schema::create('escrow_box_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escrow_box_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 50)->index();
            $table->string('actor_type', 20);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_side', 10)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
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
        Schema::dropIfExists('escrow_box_events');
        Schema::dropIfExists('escrow_box_media');
        Schema::dropIfExists('escrow_box_handover_steps');
        Schema::dropIfExists('escrow_box_payment_obligations');
        Schema::dropIfExists('escrow_box_agreement_versions');
        Schema::dropIfExists('escrow_boxes');
        Schema::dropIfExists('escrow_fee_rules');
        Schema::dropIfExists('transaction_asset_snapshots');
        Schema::dropIfExists('marketplace_export_requests');
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
