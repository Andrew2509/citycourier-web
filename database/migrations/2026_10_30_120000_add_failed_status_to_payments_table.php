<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Status pembayaran mengikuti verdict resmi iPaymu (success/failed/pending).
     * Enum lama hanya mengenal pending/paid/expired/canceled, sehingga inquiry
     * yang menyatakan transaksi gagal/kadaluarsa di sisi iPaymu memicu error
     * MySQL (data truncated) saat status lokal disimpan sebagai 'failed'.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE `payments` MODIFY `status` "
            . "ENUM('pending','paid','expired','canceled','failed') NOT NULL DEFAULT 'pending'"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('payments')->where('status', 'failed')->update(['status' => 'canceled']);

        DB::statement(
            "ALTER TABLE `payments` MODIFY `status` "
            . "ENUM('pending','paid','expired','canceled') NOT NULL DEFAULT 'pending'"
        );
    }
};
