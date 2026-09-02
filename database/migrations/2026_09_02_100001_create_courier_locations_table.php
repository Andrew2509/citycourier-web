<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courier_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courier_id')->constrained()->onDelete('cascade');
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index('courier_id');
            $table->index('shipment_id');
            $table->index('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_locations');
    }
};
