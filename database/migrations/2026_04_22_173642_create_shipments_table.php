<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('shipment_number')->unique();

            // Customer (nullable for guest users)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone');

            // Sender info
            $table->string('sender_name');
            $table->string('sender_phone');
            $table->text('sender_address');
            $table->string('origin_name')->nullable();   // Kecamatan name
            $table->string('origin_id')->nullable();     // Kecamatan ID for Komerce

            // Receiver info
            $table->string('receiver_name');
            $table->string('receiver_phone');
            $table->text('receiver_address');
            $table->string('destination_name')->nullable();  // Kecamatan name
            $table->string('destination_id')->nullable();    // Kecamatan ID for Komerce

            // Package info
            $table->string('package_description')->nullable();
            $table->decimal('package_weight', 8, 2)->default(0); // in kg

            // Courier info (from Komerce API)
            $table->string('courier_code')->nullable();    // e.g., jne, pos, tiki
            $table->string('courier_name')->nullable();    // e.g., JNE, POS, TIKI
            $table->string('courier_service')->nullable(); // e.g., REG, YES, OKE
            $table->string('etd')->nullable();             // Estimated time of delivery

            // Costs (in Rupiah)
            $table->bigInteger('shipping_cost')->default(0);
            $table->boolean('insurance')->default(false);
            $table->boolean('wood_packing')->default(false);
            $table->bigInteger('total_cost')->default(0);

            // Status & Tracking
            $table->enum('status', [
                'pending',      // Menunggu konfirmasi admin
                'confirmed',    // Dikonfirmasi admin
                'picked_up',    // Paket sudah diambil kurir/diantar ke ekspedisi
                'in_transit',   // Dalam perjalanan
                'delivered',    // Terkirim
                'cancelled',    // Dibatalkan
            ])->default('pending');
            $table->string('tracking_number')->nullable(); // Nomor resi dari ekspedisi
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
