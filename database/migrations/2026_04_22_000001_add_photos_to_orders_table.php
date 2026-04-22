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
        Schema::table('orders', function (Blueprint $column) {
            $column->string('pickup_photo')->nullable()->after('notes');
            $column->string('delivery_photo')->nullable()->after('pickup_photo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $column) {
            $column->dropColumn(['pickup_photo', 'delivery_photo']);
        });
    }
};
