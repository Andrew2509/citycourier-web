<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sinkron struktur dana_connections dengan PRD CityCourier DANA v2.0.
 * Menambahkan kolom untuk: external_id, state_hash, dana_user_reference,
 * dan mengubah kolom token menjadi *-encrypted (token at rest).
 *
 * Tabung terpisah (dana_bindings) TIDAK dibuat — kita pertahankan satu baris
 * per kurir di dana_connections agar FK withdrawals.dana_connection_id tetap
 * valid, namun mencontoh schema binding PRD (external_id, dana_user_reference,
 * token_expires_at, bound_at, revoked_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dana_connections', function (Blueprint $table) {
            if (!Schema::hasColumn('dana_connections', 'external_id')) {
                $table->string('external_id')->nullable()->unique()->after('session_id');
            }
            if (!Schema::hasColumn('dana_connections', 'state_hash')) {
                $table->string('state_hash')->nullable()->after('external_id');
            }
            if (!Schema::hasColumn('dana_connections', 'dana_user_reference')) {
                $table->string('dana_user_reference')->nullable()->after('provider_reference');
            }
            if (!Schema::hasColumn('dana_connections', 'bound_at')) {
                $table->timestamp('bound_at')->nullable()->after('token_expires_at');
            }

            // Token disimpan terenkripsi at rest → rename kolom ke *_encrypted.
            foreach (['access_token', 'refresh_token'] as $col) {
                $enc = $col . '_encrypted';
                if (Schema::hasColumn('dana_connections', $col) && !Schema::hasColumn('dana_connections', $enc)) {
                    $table->renameColumn($col, $enc);
                }
            }
        });

        // Enkripsi ulang token lama yang masih plaintext (data pre-PRD),
        // sehingga accessor decryption pada model selalu berhasil.
        $rows = DB::table('dana_connections')->whereNotNull('access_token_encrypted')->orWhereNotNull('refresh_token_encrypted')->get();
        foreach ($rows as $row) {
            $update = [];
            foreach (['access_token_encrypted', 'refresh_token_encrypted'] as $col) {
                $value = $row->{$col};
                if (!empty($value) && !$this->isEncrypted($value)) {
                    $update[$col] = Crypt::encryptString($value);
                }
            }
            if ($update) {
                DB::table('dana_connections')->where('id', $row->id)->update($update);
            }
        }
    }

    /**
     * Deteksi apakah nilai sudah terenkripsi oleh Laravel Crypt (decryptable).
     */
    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function down(): void
    {
        Schema::table('dana_connections', function (Blueprint $table) {
            foreach (['external_id', 'state_hash', 'dana_user_reference', 'bound_at'] as $col) {
                if (Schema::hasColumn('dana_connections', $col)) {
                    $table->dropColumn($col);
                }
            }
            foreach (['access_token_encrypted', 'refresh_token_encrypted'] as $enc) {
                $base = str_replace('_encrypted', '', $enc);
                if (Schema::hasColumn('dana_connections', $enc) && !Schema::hasColumn('dana_connections', $base)) {
                    $table->renameColumn($enc, $base);
                }
            }
        });
    }
};