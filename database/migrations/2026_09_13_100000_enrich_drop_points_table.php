<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drop_points', function (Blueprint $table) {
            if (! Schema::hasColumn('drop_points', 'type')) {
                $table->enum('type', ['hub', 'agent', 'locker'])->default('hub')->after('name');
            }
            if (! Schema::hasColumn('drop_points', 'city')) {
                $table->string('city')->nullable()->after('address');
            }
            if (! Schema::hasColumn('drop_points', 'province')) {
                $table->string('province')->default('Jawa Timur')->after('city');
            }
            if (! Schema::hasColumn('drop_points', 'description')) {
                $table->string('description')->nullable()->after('province');
            }
            if (! Schema::hasColumn('drop_points', 'landmark')) {
                $table->string('landmark')->nullable()->after('description');
            }
            if (! Schema::hasColumn('drop_points', 'pic_name')) {
                $table->string('pic_name')->nullable()->after('phone');
            }
            if (! Schema::hasColumn('drop_points', 'open_days')) {
                $table->string('open_days')->default('Buka Setiap Hari (7 Hari)')->after('schedule');
            }
            if (! Schema::hasColumn('drop_points', 'radius_m')) {
                $table->unsignedInteger('radius_m')->default(50)->after('rating');
            }
            if (! Schema::hasColumn('drop_points', 'capacity_pct')) {
                $table->unsignedTinyInteger('capacity_pct')->default(22)->after('radius_m');
            }
            if (! Schema::hasColumn('drop_points', 'status')) {
                $table->enum('status', ['active', 'renovation', 'closed'])->default('active')->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('drop_points', function (Blueprint $table) {
            $columns = ['type', 'city', 'province', 'description', 'landmark', 'pic_name', 'open_days', 'radius_m', 'capacity_pct', 'status'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('drop_points', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};