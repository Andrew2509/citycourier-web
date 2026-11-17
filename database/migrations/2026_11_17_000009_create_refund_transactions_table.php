<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('refund_transactions')) {
            return;
        }

        Schema::create('refund_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 80)->unique();
            $table->string('idempotency_key', 120)->unique();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained('payment_transactions')->nullOnDelete();
            $table->foreignId('legacy_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('refund_method', 32)->default('wallet_credit'); // wallet_credit | gateway_refund
            $table->string('provider', 32)->default('internal');
            $table->string('mode', 20)->default('sandbox')->index();
            $table->boolean('is_simulated')->default(false);
            $table->unsignedBigInteger('amount');
            $table->string('reason', 500);
            $table->string('status', 32)->default('requested')->index(); // requested | approved | rejected | processing | completed | failed
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_notes', 500)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->string('provider_reference', 120)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at'], 'refund_user_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_transactions');
    }
};
