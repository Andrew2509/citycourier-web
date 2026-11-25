<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('call_uuid', 64)->unique();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('caller_id')->index();
            $table->unsignedBigInteger('receiver_id')->index();
            $table->string('status', 24)->default('ringing')->index();
            $table->string('end_reason', 64)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['receiver_id', 'status'], 'call_sessions_receiver_status_idx');
            $table->index(['caller_id', 'status'], 'call_sessions_caller_status_idx');
            $table->index(['conversation_id', 'status'], 'call_sessions_conv_status_idx');
            $table->index(['order_id', 'status'], 'call_sessions_order_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_sessions');
    }
};
