<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('topup_transactions')) {
            return;
        }

        Schema::create('topup_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 80)->unique();
            $table->string('idempotency_key', 120)->unique();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('wallet_id')->constrained('wallets')->onDelete('cascade');
            $table->string('provider', 32)->index();
            $table->string('mode', 20)->default('sandbox')->index();
            $table->boolean('is_simulated')->default(false);
            $table->string('payment_type', 24); // va | qris | ewallet
            $table->string('channel_code', 64);
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('fee')->default(0);
            $table->unsignedBigInteger('net_amount');
            $table->string('status', 32)->default('pending')->index();
            $table->string('provider_reference', 120)->nullable()->index();
            $table->string('va_number', 64)->nullable();
            $table->text('qr_string')->nullable();
            $table->text('payment_url')->nullable();
            $table->json('instructions')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at'], 'topup_user_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topup_transactions');
    }
};
