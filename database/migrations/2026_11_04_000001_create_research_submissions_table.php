<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jawaban Studi Pendahuluan (penelitian Tugas Akhir UI/UX City Courier).
     *
     * Jawaban per bagian disimpan sebagai JSON terstruktur agar pertanyaan
     * dapat berkembang tanpa mengubah skema, sementara kolom skalar dipakai
     * untuk kebutuhan penyaringan/analisis cepat.
     */
    public function up(): void
    {
        Schema::create('research_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code', 24)->unique();
            $table->uuid('submission_token')->nullable()->unique();
            $table->string('role', 20);
            $table->string('age_range', 40);
            $table->boolean('has_used_service')->default(false);
            $table->json('services_used')->nullable();
            $table->string('services_used_other', 120)->nullable();
            $table->string('usage_frequency', 60);

            $table->boolean('customer_section_applicable')->default(false);
            $table->json('customer_answers')->nullable();

            $table->boolean('courier_section_applicable')->default(false);
            $table->string('courier_status', 20)->nullable();
            $table->json('courier_answers')->nullable();

            $table->string('interview_willing', 10)->nullable();
            $table->text('contact')->nullable();
            $table->text('additional_suggestion')->nullable();

            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index('submitted_at');
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_submissions');
    }
};
