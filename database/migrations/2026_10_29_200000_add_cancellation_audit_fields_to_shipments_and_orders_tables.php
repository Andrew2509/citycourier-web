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
            if (!Schema::hasColumn('shipments', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('shipments', 'cancelled_by')) {
                $table->string('cancelled_by', 50)->nullable()->after('cancelled_at');
            }
            if (!Schema::hasColumn('shipments', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('cancelled_by');
            }
            if (!Schema::hasColumn('shipments', 'cancelled_from_status')) {
                $table->string('cancelled_from_status', 50)->nullable()->after('cancellation_reason');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('delivered_at');
            }
            if (!Schema::hasColumn('orders', 'cancelled_by')) {
                $table->string('cancelled_by', 50)->nullable()->after('cancelled_at');
            }
            if (!Schema::hasColumn('orders', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('cancelled_by');
            }
            if (!Schema::hasColumn('orders', 'cancelled_from_status')) {
                $table->string('cancelled_from_status', 50)->nullable()->after('cancellation_reason');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $cols = array_filter([
                Schema::hasColumn('shipments', 'cancelled_at') ? 'cancelled_at' : null,
                Schema::hasColumn('shipments', 'cancelled_by') ? 'cancelled_by' : null,
                Schema::hasColumn('shipments', 'cancellation_reason') ? 'cancellation_reason' : null,
                Schema::hasColumn('shipments', 'cancelled_from_status') ? 'cancelled_from_status' : null,
            ]);
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            $cols = array_filter([
                Schema::hasColumn('orders', 'cancelled_at') ? 'cancelled_at' : null,
                Schema::hasColumn('orders', 'cancelled_by') ? 'cancelled_by' : null,
                Schema::hasColumn('orders', 'cancellation_reason') ? 'cancellation_reason' : null,
                Schema::hasColumn('orders', 'cancelled_from_status') ? 'cancelled_from_status' : null,
            ]);
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
