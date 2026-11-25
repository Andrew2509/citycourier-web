<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_message_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('message_id')
                ->nullable()
                ->constrained('messages')
                ->nullOnDelete();
            $table->unsignedBigInteger('conversation_id')->nullable()->index();
            $table->string('wa_message_id', 128)->nullable()->index();
            $table->string('direction', 24)->index();
            $table->string('event_hash', 64)->unique();
            $table->boolean('signature_valid')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('processing_status', 24)->default('received')->index();
            $table->json('payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_logs');
    }
};
