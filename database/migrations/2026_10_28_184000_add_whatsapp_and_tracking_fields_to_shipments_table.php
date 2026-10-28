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
            if (!Schema::hasColumn('shipments', 'whatsapp_notification')) {
                $table->string('whatsapp_notification')->default('unsent')->after('status');
            }
            if (!Schema::hasColumn('shipments', 'whatsapp_sent_at')) {
                $table->timestamp('whatsapp_sent_at')->nullable()->after('whatsapp_notification');
            }
            if (!Schema::hasColumn('shipments', 'vehicle')) {
                $table->string('vehicle')->nullable()->after('courier_service');
            }
            if (!Schema::hasColumn('shipments', 'scheduled_pickup')) {
                $table->boolean('scheduled_pickup')->default(false)->after('total_cost');
            }
            if (!Schema::hasColumn('shipments', 'pickup_time')) {
                $table->string('pickup_time')->nullable()->after('scheduled_pickup');
            }
            if (!Schema::hasColumn('shipments', 'pickup_scheduled_at')) {
                $table->timestamp('pickup_scheduled_at')->nullable()->after('pickup_time');
            }
            if (!Schema::hasColumn('shipments', 'estimated_delivery')) {
                $table->timestamp('estimated_delivery')->nullable()->after('pickup_scheduled_at');
            }
            if (!Schema::hasColumn('shipments', 'length')) {
                $table->decimal('length', 8, 2)->nullable()->after('package_weight');
            }
            if (!Schema::hasColumn('shipments', 'width')) {
                $table->decimal('width', 8, 2)->nullable()->after('length');
            }
            if (!Schema::hasColumn('shipments', 'height')) {
                $table->decimal('height', 8, 2)->nullable()->after('width');
            }
            if (!Schema::hasColumn('shipments', 'volume')) {
                $table->decimal('volume', 10, 2)->nullable()->after('height');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_notification',
                'whatsapp_sent_at',
                'vehicle',
                'scheduled_pickup',
                'pickup_time',
                'pickup_scheduled_at',
                'estimated_delivery',
                'length',
                'width',
                'height',
                'volume',
            ]);
        });
    }
};

