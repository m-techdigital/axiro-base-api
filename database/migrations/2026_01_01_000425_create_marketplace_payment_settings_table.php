<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_payment_settings', function (Blueprint $table) {
            $table->id();
            $table->string('bank_id', 32);
            $table->string('bank_name', 120);
            $table->string('account_no', 80);
            $table->string('account_name', 180);
            $table->string('qr_template', 32)->default('compact2');
            $table->string('transfer_prefix', 32)->default('MBN');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_payment_settings');
    }
};
