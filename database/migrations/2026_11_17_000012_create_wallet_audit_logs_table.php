<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wallet_audit_logs')) {
            return;
        }

        Schema::create('wallet_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_type', 32)->default('system'); // system | customer | courier | admin | webhook
            $table->foreignId('wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
            $table->string('action', 80)->index();
            $table->string('target_type', 80)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['wallet_id', 'created_at'], 'wal_wallet_created_index');
            $table->index(['target_type', 'target_id'], 'wal_target_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_audit_logs');
    }
};
