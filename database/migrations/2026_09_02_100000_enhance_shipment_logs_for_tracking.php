<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_logs', function (Blueprint $table) {
            $table->foreignId('courier_id')->nullable()->after('shipment_id')->constrained()->nullOnDelete();
            $table->decimal('latitude', 10, 8)->nullable()->after('courier_id');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            $table->decimal('accuracy', 8, 2)->nullable()->after('longitude');
            $table->index('courier_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('shipment_logs', function (Blueprint $table) {
            $table->dropForeign(['courier_id']);
            $table->dropColumn(['courier_id', 'latitude', 'longitude', 'accuracy']);
        });
    }
};
