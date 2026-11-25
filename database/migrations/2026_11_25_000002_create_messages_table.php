<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('sender_user_id')->nullable()->index();
            $table->string('sender_role', 24);
            $table->string('client_message_id', 64)->nullable();
            $table->string('channel', 24)->default('internal')->index();
            $table->string('message_type', 24)->default('text');
            $table->text('body')->nullable();
            $table->string('media_path', 512)->nullable();
            $table->string('media_mime', 128)->nullable();
            $table->unsignedBigInteger('media_size')->nullable();
            $table->string('delivery_status', 24)->default('sent')->index();
            $table->string('wa_message_id', 128)->nullable()->index();
            $table->string('wa_status', 32)->nullable();
            $table->string('wa_error_code', 64)->nullable();
            $table->string('wa_error_message', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'client_message_id'], 'msg_conv_client_id_unique');
            $table->index(['conversation_id', 'id'], 'msg_conv_cursor_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
