<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambahkan kolom token DANA ke tabel dana_connections.
     * Kolom ini dipakai oleh DanaService::completeBinding (applyToken).
     */
    public function up(): void
    {
        Schema::table('dana_connections', function (Blueprint $table) {
            if (!Schema::hasColumn('dana_connections', 'access_token')) {
                $table->text('access_token')->nullable()->after('provider_reference');
            }
            if (!Schema::hasColumn('dana_connections', 'refresh_token')) {
                $table->text('refresh_token')->nullable()->after('access_token');
            }
            if (!Schema::hasColumn('dana_connections', 'token_expires_at')) {
                $table->timestamp('token_expires_at')->nullable()->after('refresh_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dana_connections', function (Blueprint $table) {
            foreach (['access_token', 'refresh_token', 'token_expires_at'] as $col) {
                if (Schema::hasColumn('dana_connections', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
