<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->enum('status', [
                'pending',      // Menunggu konfirmasi admin
                'confirmed',    // Dikonfirmasi admin
                'assigned',     // Kurir ditugaskan
                'picking_up',   // Kurir menuju lokasi pengirim
                'picked_up',    // Paket sudah diambil kurir
                'delivering',   // Dalam perjalanan ke penerima
                'delivered',    // Terkirim
                'cancelled',    // Dibatalkan
            ])->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->enum('status', [
                'pending',
                'confirmed',
                'picked_up',
                'in_transit',
                'delivered',
                'cancelled',
            ])->default('pending')->change();
        });
    }
};
