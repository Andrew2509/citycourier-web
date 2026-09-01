<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambahkan kolom wallet/DANA ke tabel `withdrawals` yang sudah ada,
     * dan perluas enum status agar mendukung status wallet (processing,
     * success, failed, reversed, cancelled).
     */
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            if (!Schema::hasColumn('withdrawals', 'wallet_id')) {
                $table->foreignId('wallet_id')->nullable()->after('courier_id')->constrained()->onDelete('cascade');
            }
            if (!Schema::hasColumn('withdrawals', 'dana_connection_id')) {
                $table->foreignId('dana_connection_id')->nullable()->after('wallet_id')->constrained()->onDelete('set null');
            }
            foreach (['fee', 'net_amount'] as $col) {
                if (!Schema::hasColumn('withdrawals', $col)) {
                    $table->decimal($col, 15, 2)->default(0)->after('amount');
                }
            }
            if (!Schema::hasColumn('withdrawals', 'reference')) {
                $table->string('reference')->nullable()->after('net_amount')->unique();
            }
            if (!Schema::hasColumn('withdrawals', 'provider_reference')) {
                $table->string('provider_reference')->nullable()->after('reference');
            }
            if (!Schema::hasColumn('withdrawals', 'failure_reason')) {
                $table->text('failure_reason')->nullable()->after('provider_reference');
            }
        });

        // Perluas enum status: tambahkan status wallet tanpa menghapus status lama.
        if (Schema::hasTable('withdrawals')) {
            DB::statement("ALTER TABLE withdrawals MODIFY COLUMN status ENUM('pending','approved','rejected','completed','processing','success','failed','reversed','cancelled') NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropForeign(['wallet_id']);
            $table->dropForeign(['dana_connection_id']);
            $table->dropUnique('withdrawals_reference_unique');
            foreach (['wallet_id', 'dana_connection_id', 'fee', 'net_amount', 'reference', 'provider_reference', 'failure_reason'] as $col) {
                if (Schema::hasColumn('withdrawals', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
