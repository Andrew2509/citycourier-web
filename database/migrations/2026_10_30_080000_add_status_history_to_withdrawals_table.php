<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add status_history JSON column to withdrawals.
 * Menyimpan riwayat perubahan status penarikan (untuk audit app + admin).
 * Data transaksi lama dipertahankan — kolom baru nullable + default null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->json('status_history')->nullable()->after('admin_notes');
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropColumn('status_history');
        });
    }
};
