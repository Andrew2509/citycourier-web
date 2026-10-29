<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('order_rejections')) {
            Schema::create('order_rejections', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->index();
                $table->unsignedBigInteger('shipment_id')->nullable()->index();
                $table->unsignedBigInteger('courier_id')->index();
                $table->string('reason');
                $table->text('notes')->nullable();
                $table->timestamp('rejected_at')->useCurrent();
                $table->timestamps();

                $table->unique(['order_id', 'courier_id'], 'order_rejections_order_courier_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_rejections');
    }
};
