<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courier_id')->constrained()->onDelete('cascade');
            $table->string('status')->default('not_active'); // not_active, active, suspended
            $table->decimal('available_balance', 15, 2)->default(0);
            $table->decimal('pending_balance', 15, 2)->default(0);
            $table->string('currency', 10)->default('IDR');
            $table->timestamps();
            
            $table->unique('courier_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
