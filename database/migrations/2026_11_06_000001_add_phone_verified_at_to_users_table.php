<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Status verifikasi nomor telepon disimpan terpisah dari kolom `phone`.
     *
     * Nomor yang hanya diisi lewat profil atau berasal dari Google tidak
     * otomatis dianggap terverifikasi. Status ini hanya diisi backend setelah
     * kode OTP diverifikasi.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone_verified_at');
        });
    }
};
