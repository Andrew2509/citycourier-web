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
        Schema::table('couriers', function (Blueprint $table) {
            if (!Schema::hasColumn('couriers', 'nik')) {
                $table->string('nik', 16)->after('user_id')->nullable();
            }
            if (!Schema::hasColumn('couriers', 'city')) {
                $table->string('city')->after('address')->nullable();
            }
            if (!Schema::hasColumn('couriers', 'vehicle_brand')) {
                $table->string('vehicle_brand')->after('vehicle_type')->nullable();
            }
            if (!Schema::hasColumn('couriers', 'vehicle_year')) {
                $table->string('vehicle_year', 4)->after('vehicle_brand')->nullable();
            }
            if (!Schema::hasColumn('couriers', 'skck_photo')) {
                $table->string('skck_photo')->after('driving_license_photo')->nullable();
            }
            
            // Update enum by modifying the column, keeping 'mobil' for existing data
            $table->enum('vehicle_type', ['motor', 'mobil', 'pickup', 'box', 'truck', 'sepeda'])->default('motor')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('couriers', function (Blueprint $table) {
            $table->dropColumn(['nik', 'city', 'vehicle_brand', 'vehicle_year', 'skck_photo']);
            $table->enum('vehicle_type', ['motor', 'mobil', 'sepeda'])->default('motor')->change();
        });
    }
};
