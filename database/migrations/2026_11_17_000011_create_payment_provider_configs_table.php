<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_provider_configs')) {
            return;
        }

        Schema::create('payment_provider_configs', function (Blueprint $table) {
            $table->id();
            $table->string('provider_code', 32)->unique();
            $table->string('display_name', 80);
            $table->string('mode', 20)->default('sandbox');
            $table->boolean('is_active')->default(false);
            $table->boolean('supports_money_in')->default(true);
            $table->boolean('supports_money_out')->default(false);
            $table->boolean('supports_customer_withdrawal')->default(false);
            $table->json('capabilities')->nullable();
            $table->json('settings')->nullable();
            $table->string('health_status', 32)->default('unknown');
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_provider_configs');
    }
};
