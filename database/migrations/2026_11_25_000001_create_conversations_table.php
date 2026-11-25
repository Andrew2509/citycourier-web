<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable()->unique();
            $table->unsignedBigInteger('shipment_id')->nullable()->unique();
            $table->string('order_number', 64)->nullable()->index();
            $table->unsignedBigInteger('customer_user_id')->index();
            $table->unsignedBigInteger('courier_user_id')->nullable()->index();
            $table->unsignedBigInteger('courier_id')->nullable()->index();
            $table->string('status', 24)->default('active')->index();
            $table->boolean('wa_bridged_enabled')->default(true);
            $table->unsignedBigInteger('last_message_id')->nullable()->index();
            $table->string('last_message_preview', 255)->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->unsignedInteger('customer_unread_count')->default(0);
            $table->unsignedInteger('courier_unread_count')->default(0);
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['customer_user_id', 'status', 'last_message_at'], 'conv_customer_inbox_idx');
            $table->index(['courier_user_id', 'status', 'last_message_at'], 'conv_courier_inbox_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
