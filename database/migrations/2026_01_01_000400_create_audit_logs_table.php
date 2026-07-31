<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('audit_type', 40)->index();
            $table->string('event_type', 80)->index();
            $table->string('risk_level', 20)->default('normal')->index();

            $table->string('actor_type', 30)->nullable()->index();
            $table->unsignedBigInteger('actor_id')->nullable()->index();

            $table->string('entity_type', 80)->nullable()->index();
            $table->string('entity_id', 80)->nullable()->index();
            $table->string('context_type', 80)->nullable()->index();
            $table->string('context_id', 80)->nullable()->index();

            $table->uuid('request_id')->nullable()->index();
            $table->uuid('correlation_id')->nullable()->index();
            $table->string('route_name')->nullable();
            $table->string('path')->nullable();
            $table->string('method', 10)->nullable();
            $table->unsignedSmallInteger('status_code')->nullable()->index();

            $table->string('title');
            $table->text('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('changed_fields')->nullable();
            $table->json('validation_errors')->nullable();
            $table->json('metadata')->nullable();

            $table->ipAddress('ip_address')->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id', 'created_at'], 'audit_entity_created_idx');
            $table->index(['context_type', 'context_id', 'created_at'], 'audit_context_created_idx');
            $table->index(['audit_type', 'event_type', 'created_at'], 'audit_type_event_created_idx');
            $table->index(['actor_type', 'actor_id', 'created_at'], 'audit_actor_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
