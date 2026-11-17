<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wallet_balances')) {
            return;
        }

        Schema::create('wallet_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->unique()->constrained('wallets')->onDelete('cascade');
            $table->string('currency', 8)->default('IDR');
            $table->bigInteger('available_balance')->default(0);
            $table->bigInteger('pending_balance')->default(0);
            $table->bigInteger('reserved_balance')->default(0);
            $table->unsignedBigInteger('total_credited')->default(0);
            $table->unsignedBigInteger('total_debited')->default(0);
            $table->unsignedBigInteger('last_ledger_entry_id')->nullable();
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_balances');
    }
};
