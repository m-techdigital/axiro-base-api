<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('slug')->nullable()->unique();
            $table->string('product_type', 50)->default('game_account')->index();
            $table->string('delivery_method', 40)->default('account_credentials')->index();
            $table->unsignedSmallInteger('inspection_period_minutes')->default(30);
            $table->boolean('requires_pre_handover_snapshot')->default(false);
            $table->string('game_code', 50)->nullable()->index();
            $table->string('server_name', 100)->nullable();
            $table->unsignedInteger('level')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->string('approval_status', 30)->default('pending')->index();
            $table->boolean('is_published')->default(false)->index();
            $table->decimal('sale_price', 18, 2)->nullable();
            $table->decimal('sale_deposit_amount', 18, 2)->default(0);
            $table->boolean('installment_enabled')->default(false)->index();
            $table->unsignedTinyInteger('max_installment_count')->nullable();
            $table->decimal('minimum_initial_payment', 18, 2)->nullable();
            $table->string('installment_interval_unit', 20)->default('week');
            $table->unsignedInteger('installment_interval_count')->default(1);
            $table->decimal('rental_price', 18, 2)->nullable();
            $table->string('rental_price_unit', 20)->nullable();
            $table->unsignedInteger('minimum_rental_period')->nullable();
            $table->string('rental_period_unit', 20)->nullable();
            $table->string('rental_billing_mode', 20)->default('upfront');
            $table->string('rental_billing_cycle_unit', 20)->nullable();
            $table->unsignedInteger('rental_billing_cycle_count')->nullable();
            $table->decimal('rental_deposit_amount', 18, 2)->default(0);
            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_until')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->string('availability_status', 30)->default('available')->index();
            $table->foreignId('held_by_transaction_id')->nullable();
            $table->timestamp('hold_expires_at')->nullable();
            $table->unsignedInteger('availability_version')->default(1);
            $table->text('unavailable_reason')->nullable();
            $table->text('description')->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->json('image_urls')->nullable();
            $table->json('attributes')->nullable();
            $table->foreignId('owner_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['game_code', 'product_type', 'status']);
        });
        Schema::create('product_rental_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('label', 100);
            $table->string('period_unit', 20);
            $table->unsignedInteger('period_count');
            $table->decimal('price', 18, 2);
            $table->decimal('deposit_amount', 18, 2)->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['product_id', 'period_unit', 'period_count'], 'product_rental_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_rental_rates');
        Schema::dropIfExists('products');
    }
};
