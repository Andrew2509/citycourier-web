<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('couriers')) {
            return;
        }

        Schema::table('couriers', function (Blueprint $table): void {
            if (! Schema::hasColumn('couriers', 'work_status')) {
                $table->string('work_status', 24)->nullable()->after('is_active');
            }
            if (! Schema::hasColumn('couriers', 'status_changed_at')) {
                $table->timestamp('status_changed_at')->nullable()->after('work_status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('couriers')) {
            return;
        }

        Schema::table('couriers', function (Blueprint $table): void {
            $cols = array_filter([
                Schema::hasColumn('couriers', 'work_status') ? 'work_status' : null,
                Schema::hasColumn('couriers', 'status_changed_at') ? 'status_changed_at' : null,
            ]);
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
