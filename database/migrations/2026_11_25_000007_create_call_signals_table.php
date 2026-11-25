<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_signals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('call_session_id')
                ->constrained('call_sessions')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('sender_id')->index();
            $table->unsignedBigInteger('receiver_id')->index();
            $table->string('signal_type', 24);
            $table->text('payload');
            $table->timestamp('created_at')->nullable();

            $table->index(['call_session_id', 'receiver_id', 'id'], 'call_signals_poll_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_signals');
    }
};
