<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('marketplace_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->nullable()->constrained('product_listings')->nullOnDelete();
            $table->foreignId('reviewer_customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('reviewee_customer_id')->constrained('customers')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->json('criteria')->nullable();
            $table->text('comment')->nullable();
            $table->string('status', 20)->default('published')->index();
            $table->text('moderation_note')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamps();
            $table->unique(['transaction_id','reviewer_customer_id']);
            $table->index(['reviewee_customer_id','status']);
        });
        Schema::create('listing_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained('product_listings')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['customer_id','listing_id']);
        });
        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('filters');
            $table->boolean('notify')->default(true);
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamps();
        });
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('category', 40);
            $table->boolean('in_app')->default(true);
            $table->boolean('email')->default(true);
            $table->boolean('push')->default(false);
            $table->timestamps();
            $table->unique(['customer_id','category']);
        });
        Schema::create('content_entries', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30)->index();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->longText('body');
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 20)->default('draft')->index();
            $table->boolean('requires_acceptance')->default(false);
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('marketplace_risk_flags', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('subject_type', 40)->index();
            $table->unsignedBigInteger('subject_id')->index();
            $table->string('rule_code', 80)->index();
            $table->string('level', 20)->default('medium')->index();
            $table->string('status', 20)->default('open')->index();
            $table->text('reason');
            $table->json('evidence')->nullable();
            $table->text('resolution')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['subject_type','subject_id','status']);
        });
        Schema::table('customer_refresh_tokens', function (Blueprint $table) {
            $table->string('ip_address', 64)->nullable()->after('token');
            $table->text('user_agent')->nullable()->after('ip_address');
            $table->timestamp('last_used_at')->nullable()->after('user_agent');
        });
    }
    public function down(): void {
        Schema::table('customer_refresh_tokens', fn(Blueprint $table) => $table->dropColumn(['ip_address','user_agent','last_used_at']));
        Schema::dropIfExists('marketplace_risk_flags');
        Schema::dropIfExists('content_entries');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('saved_searches');
        Schema::dropIfExists('listing_favorites');
        Schema::dropIfExists('marketplace_reviews');
    }
};
