<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migrasi dukungan metode pencairan dompet kurir OVO (menggantikan DANA untuk transaksi baru
     * sambil tetap mempertahankan histori transaksi DANA yang sudah ada).
     */
    public function up(): void
    {
        if (Schema::hasTable('dana_connections')) {
            Schema::table('dana_connections', function (Blueprint $table) {
                if (!Schema::hasColumn('dana_connections', 'provider')) {
                    $table->string('provider', 20)->default('ovo');
                }
                if (!Schema::hasColumn('dana_connections', 'account_name')) {
                    $table->string('account_name', 150)->nullable();
                }
                if (!Schema::hasColumn('dana_connections', 'phone_encrypted')) {
                    $table->text('phone_encrypted')->nullable();
                }
                if (!Schema::hasColumn('dana_connections', 'consent_at')) {
                    $table->timestamp('consent_at')->nullable();
                }
            });

            // Pertahankan koneksi lama yang menggunakan dana_user_reference sebagai provider 'dana'
            DB::table('dana_connections')
                ->whereNotNull('dana_user_reference')
                ->whereNull('phone_encrypted')
                ->update(['provider' => 'dana']);
        }

        if (Schema::hasTable('withdrawals')) {
            Schema::table('withdrawals', function (Blueprint $table) {
                if (!Schema::hasColumn('withdrawals', 'provider')) {
                    $table->string('provider', 20)->default('ovo');
                }
            });

            if (DB::getDriverName() === 'mysql') {
                if (Schema::hasColumn('withdrawals', 'bank_name')) {
                    DB::statement("ALTER TABLE withdrawals MODIFY COLUMN bank_name VARCHAR(100) NULL DEFAULT 'OVO'");
                }
                if (Schema::hasColumn('withdrawals', 'account_number')) {
                    DB::statement("ALTER TABLE withdrawals MODIFY COLUMN account_number VARCHAR(100) NULL");
                }
                if (Schema::hasColumn('withdrawals', 'account_name')) {
                    DB::statement("ALTER TABLE withdrawals MODIFY COLUMN account_name VARCHAR(150) NULL");
                }
                DB::statement("ALTER TABLE withdrawals MODIFY COLUMN status ENUM('pending','reserved','approved','rejected','completed','processing','success','failed','reversed','cancelled') NOT NULL DEFAULT 'pending'");
            }

            // Pertahankan histori penarikan lama yang memakai DANA
            DB::table('withdrawals')
                ->where(function ($q) {
                    $q->where('bank_name', 'DANA')
                      ->orWhere('provider_reference', 'like', 'MOCK-DISB-%')
                      ->orWhere('provider_reference', 'like', 'DANA-%');
                })
                ->update(['provider' => 'dana']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dana_connections')) {
            Schema::table('dana_connections', function (Blueprint $table) {
                foreach (['provider', 'account_name', 'phone_encrypted', 'consent_at'] as $col) {
                    if (Schema::hasColumn('dana_connections', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('withdrawals')) {
            Schema::table('withdrawals', function (Blueprint $table) {
                if (Schema::hasColumn('withdrawals', 'provider')) {
                    $table->dropColumn('provider');
                }
            });
        }
    }
};

