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
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'courier_cancel_status')) {
                $table->string('courier_cancel_status', 30)->nullable()->after('cancelled_from_status');
            }
            if (!Schema::hasColumn('orders', 'courier_cancel_reason')) {
                $table->string('courier_cancel_reason', 255)->nullable()->after('courier_cancel_status');
            }
            if (!Schema::hasColumn('orders', 'courier_cancel_notes')) {
                $table->text('courier_cancel_notes')->nullable()->after('courier_cancel_reason');
            }
            if (!Schema::hasColumn('orders', 'courier_cancelled_at')) {
                $table->timestamp('courier_cancelled_at')->nullable()->after('courier_cancel_notes');
            }
            if (!Schema::hasColumn('orders', 'courier_cancelled_by_id')) {
                $table->unsignedBigInteger('courier_cancelled_by_id')->nullable()->after('courier_cancelled_at');
            }
            if (!Schema::hasColumn('orders', 'courier_cancel_priority')) {
                $table->string('courier_cancel_priority', 20)->nullable()->default('normal')->after('courier_cancelled_by_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $cols = array_filter([
                Schema::hasColumn('orders', 'courier_cancel_status') ? 'courier_cancel_status' : null,
                Schema::hasColumn('orders', 'courier_cancel_reason') ? 'courier_cancel_reason' : null,
                Schema::hasColumn('orders', 'courier_cancel_notes') ? 'courier_cancel_notes' : null,
                Schema::hasColumn('orders', 'courier_cancelled_at') ? 'courier_cancelled_at' : null,
                Schema::hasColumn('orders', 'courier_cancelled_by_id') ? 'courier_cancelled_by_id' : null,
                Schema::hasColumn('orders', 'courier_cancel_priority') ? 'courier_cancel_priority' : null,
            ]);
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
