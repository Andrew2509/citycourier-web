<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_addresses', function (Blueprint $table) {
            $table->decimal('latitude', 10, 8)->nullable()->after('is_favorite');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->decimal('sender_latitude', 10, 8)->nullable()->after('sender_address');
            $table->decimal('sender_longitude', 11, 8)->nullable()->after('sender_latitude');
            $table->decimal('receiver_latitude', 10, 8)->nullable()->after('receiver_address');
            $table->decimal('receiver_longitude', 11, 8)->nullable()->after('receiver_latitude');
        });
    }

    public function down(): void
    {
        Schema::table('saved_addresses', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['sender_latitude', 'sender_longitude', 'receiver_latitude', 'receiver_longitude']);
        });
    }
};
