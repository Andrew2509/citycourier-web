<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // Relasi ke user & order (nullable karena payment bisa untuk layanan lain)
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('set null');

            // Data dari Komerce Payment API
            $table->string('payment_id')->unique()->nullable(); // ID dari Komerce
            $table->enum('payment_type', ['bank_transfer', 'qris'])->default('bank_transfer');
            $table->string('channel_code', 20)->nullable(); // BCA, BNI, dll
            $table->unsignedBigInteger('amount');            // Nominal dalam rupiah

            // Status pembayaran
            $table->enum('status', ['pending', 'paid', 'expired', 'canceled'])->default('pending');

            // Instruksi pembayaran
            $table->string('va_number', 30)->nullable();    // Nomor Virtual Account
            $table->text('qr_string')->nullable();          // String untuk generate QR code
            $table->string('payment_url')->nullable();      // URL halaman pay.komerce.id

            // Waktu
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            // Data tambahan
            $table->json('callback_data')->nullable();      // Raw callback dari Komerce
            $table->json('metadata')->nullable();           // Info tambahan (order summary, dll)

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

