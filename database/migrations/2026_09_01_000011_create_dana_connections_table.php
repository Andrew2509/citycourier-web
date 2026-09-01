<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dana_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courier_id')->constrained()->onDelete('cascade');
            $table->string('status')->default('not_connected'); // not_connected, pending, connected, expired, revoked, failed
            $table->string('masked_phone')->nullable();
            $table->string('provider_reference')->nullable(); // DANA's reference ID
            $table->string('session_id')->nullable(); // For linking session
            $table->timestamp('linked_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('session_expires_at')->nullable();
            $table->timestamps();
            
            $table->unique('courier_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dana_connections');
    }
};
