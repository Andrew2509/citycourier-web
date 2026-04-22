<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Contact info
            $table->string('name');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();

            // Region IDs (for Komerce API)
            $table->string('province_id')->nullable();
            $table->string('province_name')->nullable();
            $table->string('city_id')->nullable();
            $table->string('city_name')->nullable();
            $table->string('subdistrict_id')->nullable();   // kecamatan
            $table->string('subdistrict_name')->nullable(); // kecamatan name

            // Favorite flag
            $table->boolean('is_favorite')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_addresses');
    }
};
