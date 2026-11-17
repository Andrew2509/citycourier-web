<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payout_transactions')) {
            return;
        }

        Schema::create('payout_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 80)->unique();
            $table->string('idempotency_key', 120)->unique();
            $table->string('owner_type', 24)->index(); // customer | courier
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('courier_id')->nullable()->constrained('couriers')->nullOnDelete();
            $table->foreignId('wallet_id')->constrained('wallets')->onDelete('cascade');
            $table->foreignId('payout_destination_id')->nullable()->constrained('payout_destinations')->nullOnDelete();
            $table->foreignId('legacy_withdrawal_id')->nullable()->constrained('withdrawals')->nullOnDelete();
            $table->string('payout_type', 32)->index(); // courier_auto_payout | courier_manual_payout | customer_withdrawal
            $table->string('provider', 32)->index();
            $table->string('mode', 20)->default('sandbox')->index();
            $table->boolean('is_simulated')->default(false);
            $table->string('channel_code', 64);
            $table->string('account_number_masked', 80);
            $table->string('account_holder_name', 150);
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('fee')->default(0);
            $table->unsignedBigInteger('net_amount');
            $table->string('status', 32)->default('pending')->index();
            $table->string('provider_reference', 120)->nullable()->index();
            $table->string('failure_code', 64)->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->json('status_history')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['courier_id', 'status', 'created_at'], 'payout_courier_status_index');
            $table->index(['user_id', 'owner_type', 'created_at'], 'payout_user_owner_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_transactions');
    }
};
