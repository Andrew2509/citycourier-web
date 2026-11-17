<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_webhooks')) {
            return;
        }

        Schema::create('payment_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32)->index();
            $table->string('event_type', 80)->index();
            $table->string('external_id', 120)->nullable()->index();
            $table->string('provider_reference', 120)->nullable()->index();
            $table->string('webhook_hash', 64)->unique();
            $table->boolean('signature_valid')->default(false);
            $table->string('processing_status', 32)->default('received')->index();
            $table->json('headers')->nullable();
            $table->json('payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhooks');
    }
};
