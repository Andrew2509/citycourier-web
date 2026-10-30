<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mengembalikan DANA sebagai metode pencairan aktif default.
 *
 * Nilai kolom provider pada baris yang sudah ada (termasuk riwayat OVO)
 * TIDAK diubah — hanya default untuk record baru yang kembali ke 'dana',
 * agar koneksi/penarikan DANA baru tidak salah tercatat sebagai OVO.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dana_connections')) {
            if (Schema::hasColumn('dana_connections', 'provider')) {
                DB::statement("ALTER TABLE dana_connections MODIFY COLUMN provider VARCHAR(20) NOT NULL DEFAULT 'dana'");
            }
        }

        if (Schema::hasTable('withdrawals')) {
            if (Schema::hasColumn('withdrawals', 'provider')) {
                DB::statement("ALTER TABLE withdrawals MODIFY COLUMN provider VARCHAR(20) NOT NULL DEFAULT 'dana'");
            }
            if (DB::getDriverName() === 'mysql' && Schema::hasColumn('withdrawals', 'bank_name')) {
                DB::statement("ALTER TABLE withdrawals MODIFY COLUMN bank_name VARCHAR(100) NULL DEFAULT 'DANA'");
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dana_connections')) {
            if (Schema::hasColumn('dana_connections', 'provider')) {
                DB::statement("ALTER TABLE dana_connections MODIFY COLUMN provider VARCHAR(20) NOT NULL DEFAULT 'ovo'");
            }
        }

        if (Schema::hasTable('withdrawals')) {
            if (Schema::hasColumn('withdrawals', 'provider')) {
                DB::statement("ALTER TABLE withdrawals MODIFY COLUMN provider VARCHAR(20) NOT NULL DEFAULT 'ovo'");
            }
            if (DB::getDriverName() === 'mysql' && Schema::hasColumn('withdrawals', 'bank_name')) {
                DB::statement("ALTER TABLE withdrawals MODIFY COLUMN bank_name VARCHAR(100) NULL DEFAULT 'OVO'");
            }
        }
    }
};
