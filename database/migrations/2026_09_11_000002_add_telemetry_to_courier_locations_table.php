<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add live telemetry columns (speed, battery) to courier_locations
     * so the Presensi & Pelacakan Kurir Live Map HUD can be data-driven.
     */
    public function up(): void
    {
        Schema::table('courier_locations', function (Blueprint $table) {
            if (! Schema::hasColumn('courier_locations', 'speed_kmh')) {
                $table->decimal('speed_kmh', 5, 1)->nullable()->after('accuracy');
            }
            if (! Schema::hasColumn('courier_locations', 'battery_percent')) {
                $table->unsignedTinyInteger('battery_percent')->nullable()->after('speed_kmh');
            }
        });
    }

    public function down(): void
    {
        Schema::table('courier_locations', function (Blueprint $table) {
            foreach (['speed_kmh', 'battery_percent'] as $col) {
                if (Schema::hasColumn('courier_locations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};