<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit log untuk seluruh komunikasi backend City Courier <-> OVO SNAP B2B.
 *
 * Catatan keamanan:
 * - JANGAN PERNAH menyimpan client_secret, private key, access token, signature,
 *   PIN, atau OTP di tabel ini. Redaksi dilakukan di OvoAuditLogger::redact().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ovo_api_logs')) {
            return;
        }

        Schema::create('ovo_api_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action', 50);                 // access_token, account_inquiry, topup, topup_status, balance_inquiry
            $table->string('endpoint', 255)->nullable();
            $table->unsignedInteger('http_status')->nullable();
            $table->string('response_code', 20)->nullable();
            $table->boolean('success')->default(false);
            $table->text('request_payload')->nullable();  // payload ter-redaksi
            $table->text('response_payload')->nullable(); // payload ter-redaksi (dipotong)
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['action', 'created_at']);
            $table->index('success');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ovo_api_logs');
    }
};
