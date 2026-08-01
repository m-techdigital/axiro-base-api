<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type')->index();
            $table->string('target_module')->default('transactions')->index();
            $table->string('status')->default('approved')->index();
            $table->unsignedInteger('version')->default(1);
            $table->json('merge_fields')->nullable();
            $table->longText('content_html');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('generated_documents', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('document_template_id')->constrained('document_templates')->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->string('document_type')->index();
            $table->string('status')->default('issued')->index();
            $table->unsignedInteger('version')->default(1);
            $table->string('title');
            $table->json('merge_payload')->nullable();
            $table->longText('rendered_html');
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['transaction_id', 'document_type', 'version'], 'generated_document_transaction_type_version_unique');
        });

        Schema::create('document_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generated_document_id')->constrained('generated_documents')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('role')->index();
            $table->string('status')->default('accepted')->index();
            $table->timestamp('accepted_at');
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['generated_document_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_acceptances');
        Schema::dropIfExists('generated_documents');
        Schema::dropIfExists('document_templates');
    }
};
