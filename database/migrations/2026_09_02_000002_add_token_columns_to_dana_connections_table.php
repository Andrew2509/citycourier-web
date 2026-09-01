<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dana_connections', function (Blueprint $table) {
            // DANA Widget Binding tokens
            if (!Schema::hasColumn('dana_connections', 'access_token')) {
                $table->text('access_token')->nullable()->after('provider_reference');
            }
            if (!Schema::hasColumn('dana_connections', 'refresh_token')) {
                $table->text('refresh_token')->nullable()->after('access_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dana_connections', function (Blueprint $table) {
            $table->dropColumn(['access_token', 'refresh_token']);
        });
    }
};
