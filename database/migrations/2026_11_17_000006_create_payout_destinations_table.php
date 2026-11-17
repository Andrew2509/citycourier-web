<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payout_destinations')) {
            return;
        }

        Schema::create('payout_destinations', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type', 24)->index(); // customer | courier
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('courier_id')->nullable()->constrained('couriers')->nullOnDelete();
            $table->string('destination_type', 24); // bank | ewallet
            $table->string('channel_code', 64);
            $table->string('channel_name', 100);
            $table->string('account_number', 80);
            $table->string('account_number_masked', 80);
            $table->string('account_holder_name', 150);
            $table->boolean('is_verified')->default(false);
            $table->string('verification_status', 32)->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'owner_type', 'is_active'], 'pd_user_owner_active_index');
            $table->index(['courier_id', 'is_default'], 'pd_courier_default_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_destinations');
    }
};
