<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sinkron struktur dana_connections dengan PRD CityCourier DANA v2.0.
 * Menambahkan kolom untuk: external_id, state_hash, dana_user_reference,
 * token_expires_at, bound_at, dan kolom token terenkripsi at rest.
 *
 * MIGRASI IDEMPOTEN: aman dijalankan ulang walau sebagian kolom sudah ada
 * (mis. server yang sebelumnya gagal di tengah, atau tabel yang pernah
 * dibangun ulang tanpa kolom token dari migrasi 000002).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Tambah kolom baru (nullable, tanpa after() agar tak bergantung
        //    pada urutan kolom yang mungkin absen di server).
        Schema::table('dana_connections', function (Blueprint $table) {
            if (!Schema::hasColumn('dana_connections', 'external_id')) {
                $table->string('external_id')->nullable()->unique();
            }
            if (!Schema::hasColumn('dana_connections', 'state_hash')) {
                $table->string('state_hash')->nullable();
            }
            if (!Schema::hasColumn('dana_connections', 'dana_user_reference')) {
                $table->string('dana_user_reference')->nullable();
            }
            if (!Schema::hasColumn('dana_connections', 'token_expires_at')) {
                $table->timestamp('token_expires_at')->nullable();
            }
            if (!Schema::hasColumn('dana_connections', 'bound_at')) {
                $table->timestamp('bound_at')->nullable();
            }
            if (!Schema::hasColumn('dana_connections', 'access_token_encrypted')) {
                $table->text('access_token_encrypted')->nullable();
            }
            if (!Schema::hasColumn('dana_connections', 'refresh_token_encrypted')) {
                $table->text('refresh_token_encrypted')->nullable();
            }
        });

        // 2) Pindahkan & enkripsi token lama (sebelum kolom lama dihapus).
        //    Sumber nilai: kolom *_encrypted bila ada, fallback kolom lama.
        $rows = DB::table('dana_connections')->get();
        foreach ($rows as $row) {
            $update = [];
            foreach (['access_token', 'refresh_token'] as $base) {
                $enc = $base . '_encrypted';
                $legacy = $row->{$base} ?? null;          // kolom lama (mungkin tidak ada)
                $current = $row->{$enc} ?? null;          // kolom baru
                $value = !empty($current) ? $current : $legacy;

                if (!empty($value) && !$this->isEncrypted($value)) {
                    $update[$enc] = Crypt::encryptString($value);
                }
            }
            if ($update) {
                DB::table('dana_connections')->where('id', $row->id)->update($update);
            }
        }

        // 3) Hapus kolom token plaintext lama (baru setelah data dipindah).
        Schema::table('dana_connections', function (Blueprint $table) {
            if (Schema::hasColumn('dana_connections', 'access_token_encrypted') && Schema::hasColumn('dana_connections', 'access_token')) {
                $table->dropColumn('access_token');
            }
            if (Schema::hasColumn('dana_connections', 'refresh_token_encrypted') && Schema::hasColumn('dana_connections', 'refresh_token')) {
                $table->dropColumn('refresh_token');
            }
        });
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
            foreach (['external_id', 'state_hash', 'dana_user_reference', 'bound_at', 'access_token_encrypted', 'refresh_token_encrypted'] as $col) {
                if (Schema::hasColumn('dana_connections', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};