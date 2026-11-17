<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ResearchSubmission extends Model
{
    protected $fillable = [
        'reference_code',
        'submission_token',
        'role',
        'age_range',
        'has_used_service',
        'services_used',
        'services_used_other',
        'usage_frequency',
        'customer_section_applicable',
        'customer_answers',
        'courier_section_applicable',
        'courier_status',
        'courier_answers',
        'interview_willing',
        'contact',
        'additional_suggestion',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'has_used_service'            => 'boolean',
            'services_used'               => 'array',
            'customer_section_applicable' => 'boolean',
            'customer_answers'            => 'array',
            'courier_section_applicable'  => 'boolean',
            'courier_answers'             => 'array',
            'contact'                     => 'encrypted',
            'submitted_at'                => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $submission) {
            if (empty($submission->reference_code)) {
                $submission->reference_code = self::generateReferenceCode();
            }

            if (empty($submission->submitted_at)) {
                $submission->submitted_at = now();
            }
        });
    }

    /**
     * Kode referensi singkat untuk ditunjukkan pada halaman terima kasih.
     * Tidak memuat data pribadi dan tidak dapat dipakai untuk membaca jawaban.
     */
    public static function generateReferenceCode(): string
    {
        do {
            $code = 'SP-' . now()->format('ymd') . '-' . Str::upper(Str::random(5));
        } while (self::where('reference_code', $code)->exists());

        return $code;
    }

    public function getRoleLabelAttribute(): ?string
    {
        return \App\Support\ResearchSurvey::ROLES[$this->role] ?? null;
    }
}
