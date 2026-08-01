<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_modes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('model_offer_modes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_mode_id')->constrained()->cascadeOnDelete();
            $table->morphs('model');
            $table->timestamps();
            $table->unique(['offer_mode_id', 'model_type', 'model_id'], 'model_offer_mode_unique');
        });
        Schema::create('product_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->timestamp('hold_until')->nullable()->index();
            $table->nullableMorphs('source');
            $table->string('status', 30)->default('active')->index();
            $table->text('note')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->string('release_reason')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'status']);
        });
        Schema::create('product_availability_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->index();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->timestamp('hold_until')->nullable();
            $table->nullableMorphs('source');
            $table->text('note')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_availability_histories');
        Schema::dropIfExists('product_holds');
        Schema::dropIfExists('model_offer_modes');
        Schema::dropIfExists('offer_modes');
    }
};
