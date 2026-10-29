<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tambahkan status 'picked_up' ke enum status tabel orders
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'assigned', 'picking_up', 'picked_up', 'delivering', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending'");

        // 2. Tambahkan kolom jadwal pickup, jenis kendaraan, dan timestamp pelacakan
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'pickup_started_at')) {
                $table->timestamp('pickup_started_at')->nullable()->after('pickup_photo');
            }
            if (!Schema::hasColumn('orders', 'scheduled_pickup')) {
                $table->boolean('scheduled_pickup')->default(false)->after('status');
            }
            if (!Schema::hasColumn('orders', 'pickup_time')) {
                $table->string('pickup_time', 50)->nullable()->after('scheduled_pickup');
            }
            if (!Schema::hasColumn('orders', 'pickup_scheduled_at')) {
                $table->timestamp('pickup_scheduled_at')->nullable()->after('pickup_time');
            }
            if (!Schema::hasColumn('orders', 'vehicle')) {
                $table->string('vehicle', 50)->nullable()->after('package_weight');
            }
            if (!Schema::hasColumn('orders', 'issue_reason')) {
                $table->string('issue_reason')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('orders', 'issue_notes')) {
                $table->text('issue_notes')->nullable()->after('issue_reason');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'pickup_started_at',
                'scheduled_pickup',
                'pickup_time',
                'pickup_scheduled_at',
                'vehicle',
                'issue_reason',
                'issue_notes',
            ]);
        });

        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'assigned', 'picking_up', 'delivering', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending'");
    }
};
