<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('product_listings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('owner_customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('listing_type', 20)->index();
            $table->string('status', 30)->default('draft')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('sale_price', 18, 2)->nullable();
            $table->decimal('rental_price', 18, 2)->nullable();
            $table->string('rental_price_unit', 20)->nullable();
            $table->unsignedInteger('minimum_rental_period')->nullable();
            $table->string('rental_period_unit', 20)->nullable();
            $table->string('rental_billing_mode', 20)->default('upfront');
            $table->string('rental_billing_cycle_unit', 20)->nullable();
            $table->unsignedInteger('rental_billing_cycle_count')->nullable();
            $table->decimal('deposit_amount', 18, 2)->default(0);
            $table->boolean('allow_installment')->default(false);
            $table->unsignedTinyInteger('max_installment_count')->nullable();
            $table->decimal('minimum_initial_payment', 18, 2)->nullable();
            $table->string('installment_interval_unit', 20)->default('week');
            $table->unsignedInteger('installment_interval_count')->default(1);
            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_until')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['product_id', 'status']);
            $table->index(['owner_customer_id', 'status']);
        });
        Schema::create('listing_rental_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_listing_id')->constrained('product_listings')->cascadeOnDelete();
            $table->string('label', 100);
            $table->string('period_unit', 20);
            $table->unsignedInteger('period_count');
            $table->decimal('price', 18, 2);
            $table->decimal('deposit_amount', 18, 2)->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['product_listing_id', 'period_unit', 'period_count'], 'listing_rental_period_unique');
        });
    }
    public function down(): void { Schema::dropIfExists('listing_rental_rates'); Schema::dropIfExists('product_listings'); }
};
