<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Memperluas kolom payments.payment_type dari ENUM('bank_transfer','qris')
     * menjadi VARCHAR(32) agar pembayaran menggunakan 'wallet' (City Wallet),
     * 'va', maupun 'ewallet' tidak gagal "Data truncated" pada MySQL.
     */
    public function up(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE `payments` MODIFY `payment_type` VARCHAR(32) NOT NULL DEFAULT 'bank_transfer'"
            );
            DB::statement(
                'ALTER TABLE `payments` MODIFY `channel_code` VARCHAR(64) NULL'
            );
            DB::statement(
                'ALTER TABLE `payments` MODIFY `payment_url` TEXT NULL'
            );

            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_type', 32)->default('bank_transfer')->change();
            $table->string('channel_code', 64)->nullable()->change();
            $table->text('payment_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payments') || DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('payments')
            ->whereNotIn('payment_type', ['bank_transfer', 'qris'])
            ->update(['payment_type' => 'bank_transfer']);

        DB::statement(
            "ALTER TABLE `payments` MODIFY `payment_type` ENUM('bank_transfer','qris') NOT NULL DEFAULT 'bank_transfer'"
        );
    }
};
