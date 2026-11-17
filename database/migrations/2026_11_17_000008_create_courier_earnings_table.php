<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('courier_earnings')) {
            return;
        }

        Schema::create('courier_earnings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 80)->unique();
            $table->foreignId('courier_id')->constrained('couriers')->onDelete('cascade');
            $table->foreignId('wallet_id')->constrained('wallets')->onDelete('cascade');
            $table->foreignId('order_id')->unique()->constrained('orders')->onDelete('cascade');
            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
            $table->unsignedBigInteger('gross_amount');
            $table->decimal('commission_rate', 5, 2)->default(10.00);
            $table->unsignedBigInteger('commission_amount');
            $table->unsignedBigInteger('net_amount');
            $table->string('status', 24)->default('pending')->index(); // pending | available | reserved | paid | disputed | reversed
            $table->foreignId('payout_transaction_id')->nullable()->constrained('payout_transactions')->nullOnDelete();
            $table->string('mode', 20)->default('sandbox')->index();
            $table->timestamp('earned_at')->nullable();
            $table->timestamp('available_at')->nullable()->index();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['courier_id', 'status'], 'ce_courier_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_earnings');
    }
};
