<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            // Add idempotency_key column (PRD §25)
            if (!Schema::hasColumn('withdrawals', 'idempotency_key')) {
                $table->string('idempotency_key', 50)->nullable()->after('reference');
                $table->unique(['courier_id', 'idempotency_key']);
            }

            // Add failure_code column (PRD §25)
            if (!Schema::hasColumn('withdrawals', 'failure_code')) {
                $table->string('failure_code')->nullable()->after('failure_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropIndex(['courier_id', 'idempotency_key']);
            $table->dropColumn(['idempotency_key', 'failure_code']);
        });
    }
};
