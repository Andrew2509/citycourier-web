<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_transactions')) {
            return;
        }

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 80)->unique();
            $table->string('idempotency_key', 120)->unique();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('legacy_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('payment_method', 32); // wallet | va | qris | ewallet | cod
            $table->string('provider', 32)->default('internal')->index();
            $table->string('mode', 20)->default('sandbox')->index();
            $table->boolean('is_simulated')->default(false);
            $table->string('channel_code', 64)->nullable();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('fee')->default(0);
            $table->unsignedBigInteger('total_amount');
            $table->string('status', 32)->default('pending')->index();
            $table->string('provider_reference', 120)->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at'], 'paytx_user_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
