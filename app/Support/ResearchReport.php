<?php

namespace App\Support;

use App\Models\ResearchSubmission;

/**
 * Penyaji jawaban Studi Pendahuluan.
 *
 * Mengubah kolom JSON pada ResearchSubmission menjadi jawaban per nomor
 * pertanyaan, sehingga daftar admin, halaman detail, PDF, dan ekspor
 * selalu menampilkan isi yang sama.
 */
class ResearchReport
{
    public const SECTIONS = [
        'A' => 'Identitas dan Pengalaman Responden',
        'B' => 'Pengalaman Customer',
        'C' => 'Pengalaman Courier',
        'D' => 'Saran dan Persetujuan',
    ];

    /** Judul lengkap pertanyaan (dipakai di halaman detail & PDF). */
    public const QUESTIONS = [
        1 => 'Peran Anda dalam layanan pengiriman barang?',
        2 => 'Berapa usia Anda?',
        3 => 'Apakah Anda pernah menggunakan layanan pengiriman barang?',
        4 => 'Layanan pengiriman apa yang pernah Anda gunakan atau ketahui?',
        5 => 'Seberapa sering Anda menggunakan layanan pengiriman barang?',
        6 => 'Aktivitas apa yang biasanya Anda lakukan menggunakan layanan pengiriman barang?',
        7 => 'Apakah Anda pernah mengalami kesulitan ketika menggunakan aplikasi layanan pengiriman barang?',
        8 => 'Pada bagian apa Anda pernah mengalami kesulitan?',
        9 => 'Seberapa mudah Anda menemukan fitur yang dibutuhkan pada aplikasi pengiriman?',
        10 => 'Seberapa mudah Anda memahami informasi status pengiriman yang ditampilkan aplikasi?',
        11 => 'Informasi apa yang menurut Anda penting ditampilkan pada aplikasi pengiriman barang?',
        12 => 'Menurut Anda, bagian apa dari aplikasi pengiriman barang yang perlu diperbaiki?',
        13 => 'Apa kendala paling sering Anda alami ketika melakukan pengiriman atau menerima paket?',
        14 => 'Fitur atau informasi apa yang Anda harapkan tersedia pada aplikasi pengiriman barang agar lebih mudah digunakan?',
        15 => 'Apakah Anda pernah bekerja sebagai kurir pengiriman barang?',
        16 => 'Aplikasi apa yang pernah Anda gunakan dalam pekerjaan sebagai kurir?',
        17 => 'Aktivitas apa yang biasa Anda lakukan dalam proses pengiriman?',
        18 => 'Apakah Anda pernah mengalami kesulitan ketika menggunakan aplikasi kurir?',
        19 => 'Pada bagian apa Anda pernah mengalami kesulitan ketika menggunakan aplikasi kurir?',
        20 => 'Seberapa mudah Anda memahami informasi pesanan yang ditampilkan pada aplikasi kurir?',
        21 => 'Seberapa mudah Anda mengikuti tahapan proses pengiriman melalui aplikasi?',
        22 => 'Informasi apa yang menurut Anda paling penting ditampilkan pada aplikasi kurir?',
        23 => 'Apa kendala yang paling sering Anda alami ketika menjalankan proses pengiriman barang?',
        24 => 'Menurut Anda, bagian apa dari aplikasi kurir yang perlu diperbaiki agar pekerjaan lebih mudah dilakukan?',
        25 => 'Fitur atau informasi apa yang Anda harapkan tersedia pada aplikasi kurir?',
        26 => 'Apakah Anda bersedia apabila dihubungi kembali untuk wawancara lanjutan terkait penelitian ini?',
        27 => 'Jika bersedia, silakan tuliskan kontak yang dapat dihubungi.',
        28 => 'Apakah Anda memiliki saran lain terkait aplikasi layanan pengiriman barang?',
    ];

    /** Judul ringkas untuk header tabel ekspor. */
    public const SHORT_LABELS = [
        1 => 'Peran responden',
        2 => 'Usia',
        3 => 'Pernah menggunakan layanan',
        4 => 'Layanan yang digunakan',
        5 => 'Frekuensi penggunaan',
        6 => 'Aktivitas Customer',
        7 => 'Kesulitan aplikasi Customer',
        8 => 'Bagian yang menyulitkan (Customer)',
        9 => 'Kemudahan menemukan fitur (1-5)',
        10 => 'Kejelasan status pengiriman (1-5)',
        11 => 'Informasi penting (Customer)',
        12 => 'Saran perbaikan (Customer)',
        13 => 'Kendala (Customer)',
        14 => 'Fitur diharapkan (Customer)',
        15 => 'Pengalaman menjadi kurir',
        16 => 'Aplikasi kurir',
        17 => 'Aktivitas Courier',
        18 => 'Kesulitan aplikasi Courier',
        19 => 'Bagian yang menyulitkan (Courier)',
        20 => 'Kejelasan informasi pesanan (1-5)',
        21 => 'Kejelasan tahapan pengiriman (1-5)',
        22 => 'Informasi penting (Courier)',
        23 => 'Kendala (Courier)',
        24 => 'Saran perbaikan (Courier)',
        25 => 'Fitur diharapkan (Courier)',
        26 => 'Kesediaan wawancara',
        27 => 'Kontak wawancara',
        28 => 'Saran tambahan',
    ];

    public static function roleLabel(?string $role): string
    {
        return ResearchSurvey::ROLES[$role] ?? '—';
    }

    public static function courierStatusLabel(?string $status): string
    {
        return ResearchSurvey::COURIER_STATUSES[$status] ?? '—';
    }

    public static function consentLabel(?string $consent): string
    {
        return ResearchSurvey::INTERVIEW_CONSENT[$consent] ?? '—';
    }

    /**
     * Seluruh jawaban, dipetakan per nomor pertanyaan (1–28).
     *
     * @return array<int, string|array<int, string>|null>
     */
    public static function answers(ResearchSubmission $submission): array
    {
        $customer = $submission->customer_answers ?? [];
        $courier = $submission->courier_answers ?? [];

        $services = $submission->services_used ?: [];
        if ($submission->services_used_other) {
            $services[] = 'Lainnya: ' . $submission->services_used_other;
        }

        return [
            1 => self::roleLabel($submission->role),
            2 => $submission->age_range,
            3 => $submission->has_used_service ? 'Ya' : 'Tidak',
            4 => self::withOther($services, null),
            5 => $submission->usage_frequency,
            6 => self::withOther($customer['activities'] ?? [], $customer['activities_other'] ?? null),
            7 => $customer['difficulty_frequency'] ?? null,
            8 => self::withOther($customer['problem_areas'] ?? [], $customer['problem_areas_other'] ?? null),
            9 => self::scale($customer['find_features_score'] ?? null),
            10 => self::scale($customer['status_clarity_score'] ?? null),
            11 => self::withOther($customer['important_info'] ?? [], $customer['important_info_other'] ?? null),
            12 => $customer['improvement_notes'] ?? null,
            13 => $customer['common_obstacles'] ?? null,
            14 => $customer['expected_features'] ?? null,
            15 => $submission->courier_status ? self::courierStatusLabel($submission->courier_status) : null,
            16 => self::withOther($courier['apps'] ?? [], $courier['apps_other'] ?? null),
            17 => self::withOther($courier['activities'] ?? [], $courier['activities_other'] ?? null),
            18 => $courier['difficulty_frequency'] ?? null,
            19 => self::withOther($courier['problem_areas'] ?? [], $courier['problem_areas_other'] ?? null),
            20 => self::scale($courier['order_clarity_score'] ?? null),
            21 => self::scale($courier['flow_clarity_score'] ?? null),
            22 => self::withOther($courier['important_info'] ?? [], $courier['important_info_other'] ?? null),
            23 => $courier['common_obstacles'] ?? null,
            24 => $courier['improvement_notes'] ?? null,
            25 => $courier['expected_features'] ?? null,
            26 => self::consentLabel($submission->interview_willing),
            27 => $submission->contact,
            28 => $submission->additional_suggestion,
        ];
    }

    /**
     * Bagian yang relevan untuk responden, lengkap dengan jawabannya.
     *
     * @return array<int, array{key: string, title: string, numbers: array<int, array{number: int, label: string, value: mixed}>}>
     */
    public static function sections(ResearchSubmission $submission): array
    {
        $answers = self::answers($submission);

        $map = [
            'A' => [1, 2, 3, 4, 5],
            'B' => $submission->customer_section_applicable ? range(6, 14) : [],
            'C' => match (true) {
                $submission->courier_section_applicable => range(15, 25),
                $submission->courier_status !== null => [15],
                default => [],
            },
            'D' => [26, 27, 28],
        ];

        $sections = [];

        foreach ($map as $key => $numbers) {
            if ($numbers === []) {
                continue;
            }

            $items = [];
            foreach ($numbers as $number) {
                $value = $answers[$number] ?? null;

                // Pertanyaan lanjutan yang tidak relevan tidak ditampilkan.
                if ($number === 27 && $submission->interview_willing !== 'ya') {
                    continue;
                }

                $items[] = [
                    'number' => $number,
                    'label' => self::QUESTIONS[$number],
                    'value' => $value,
                ];
            }

            $sections[] = [
                'key' => $key,
                'title' => self::SECTIONS[$key],
                'numbers' => $items,
                'skipped' => $numbers === [],
            ];
        }

        return $sections;
    }

    /** Ubah jawaban (termasuk array) menjadi satu baris teks untuk ekspor. */
    public static function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_array($value)) {
            return implode('; ', array_values(array_filter($value, fn ($item) => $item !== null && $item !== '')));
        }

        return (string) $value;
    }

    private static function scale(mixed $score): ?string
    {
        return $score === null || $score === '' ? null : $score . ' dari 5';
    }

    /**
     * Tambahkan isian "Lainnya" bila pilihannya dicentang.
     *
     * @param  array<int, string>  $values
     * @return array<int, string>
     */
    private static function withOther(array $values, ?string $other): array
    {
        $values = array_values(array_filter($values, fn ($value) => is_string($value) && $value !== ''));

        if ($other !== null && $other !== '') {
            $index = array_search('Lainnya', $values, true);

            if ($index !== false) {
                $values[$index] = 'Lainnya: ' . $other;
            } else {
                $values[] = $other;
            }
        }

        return $values;
    }
}
