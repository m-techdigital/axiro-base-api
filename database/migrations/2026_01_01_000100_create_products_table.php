<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('slug')->nullable()->unique();
            $table->string('product_type', 50)->default('game_account')->index();
            $table->string('game_code', 50)->nullable()->index();
            $table->string('server_name', 100)->nullable();
            $table->unsignedInteger('level')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->decimal('price', 18, 2)->default(0);
            $table->text('description')->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->json('image_urls')->nullable();
            $table->json('attributes')->nullable();
            $table->foreignId('owner_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['game_code', 'status']);
        });
    }
    public function down(): void { Schema::dropIfExists('products'); }
};
