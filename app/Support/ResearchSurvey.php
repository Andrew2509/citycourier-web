<?php

namespace App\Support;

/**
 * Katalog pertanyaan Studi Pendahuluan City Courier.
 *
 * Satu sumber kebenaran untuk pilihan jawaban yang dipakai bersama oleh
 * tampilan formulir dan aturan validasi server, sehingga tidak ada
 * kemungkinan pilihan di layar berbeda dengan yang diterima backend.
 */
class ResearchSurvey
{
    /** Batas panjang jawaban teks bebas (disamakan dengan maxlength di UI). */
    public const TEXT_LIMIT = 1000;

    public const ROLES = [
        'customer' => 'Customer / Pengguna layanan pengiriman',
        'courier' => 'Courier / Kurir pengiriman',
        'both' => 'Keduanya (Customer dan Courier)',
    ];

    public const AGE_RANGES = [
        'Di bawah 18 tahun',
        '18–25 tahun',
        '26–35 tahun',
        '36–45 tahun',
        'Di atas 45 tahun',
    ];

    public const SERVICES = [
        'J&T Express',
        'JNE',
        'GoSend',
        'GrabExpress',
        'SiCepat',
        'Lalamove',
        'Pos Indonesia',
        'Lainnya',
    ];

    public const USAGE_FREQUENCIES = [
        'Setiap hari',
        'Beberapa kali dalam seminggu',
        'Beberapa kali dalam sebulan',
        'Jarang',
        'Tidak pernah',
    ];

    public const DIFFICULTY_LEVELS = [
        'Sangat sering',
        'Sering',
        'Kadang-kadang',
        'Jarang',
        'Tidak pernah',
    ];

    public const CUSTOMER_ACTIVITIES = [
        'Mengirim barang',
        'Menerima barang',
        'Melakukan pemesanan pickup',
        'Memeriksa tarif pengiriman',
        'Melacak status pengiriman',
        'Menghubungi kurir',
        'Menghubungi layanan pelanggan',
        'Lainnya',
    ];

    public const CUSTOMER_PROBLEM_AREAS = [
        'Login atau registrasi akun',
        'Menemukan fitur yang dibutuhkan',
        'Mengisi alamat pengirim atau penerima',
        'Menentukan lokasi pickup',
        'Memahami informasi tarif pengiriman',
        'Melakukan pemesanan pengiriman',
        'Memahami status atau proses pengiriman',
        'Melacak lokasi paket',
        'Menghubungi kurir',
        'Tidak pernah mengalami kesulitan',
        'Lainnya',
    ];

    public const CUSTOMER_IMPORTANT_INFO = [
        'Informasi tarif',
        'Estimasi waktu pengiriman',
        'Status pengiriman',
        'Lokasi paket',
        'Informasi kurir',
        'Informasi alamat pengirim dan penerima',
        'Detail barang',
        'Bukti pengiriman',
        'Informasi kontak kurir',
        'Lainnya',
    ];

    public const COURIER_STATUSES = [
        'active' => 'Ya, masih aktif',
        'former' => 'Ya, tetapi sudah tidak aktif',
        'never' => 'Belum pernah',
    ];

    public const COURIER_APPS = [
        'Aplikasi J&T Express',
        'Aplikasi JNE',
        'GoSend',
        'GrabExpress',
        'Lalamove',
        'SiCepat',
        'Aplikasi lainnya',
        'Tidak pernah menggunakan aplikasi kurir',
    ];

    public const COURIER_ACTIVITIES = [
        'Menerima pesanan pengiriman',
        'Melihat informasi barang',
        'Melihat alamat pengirim',
        'Melihat alamat penerima',
        'Menentukan rute pengiriman',
        'Menghubungi Customer',
        'Mengubah status pengiriman',
        'Mengunggah bukti pengiriman',
        'Menyelesaikan pesanan',
        'Lainnya',
    ];

    public const COURIER_PROBLEM_AREAS = [
        'Memahami informasi pesanan',
        'Menemukan alamat pengirim',
        'Menemukan alamat penerima',
        'Memahami detail barang',
        'Menentukan rute pengiriman',
        'Menghubungi Customer',
        'Mengubah status pesanan',
        'Mengunggah bukti pengiriman',
        'Memahami tahapan pengiriman',
        'Tidak pernah mengalami kesulitan',
        'Lainnya',
    ];

    public const COURIER_IMPORTANT_INFO = [
        'Informasi detail pesanan',
        'Nama pengirim',
        'Alamat pengirim',
        'Nama penerima',
        'Alamat penerima',
        'Nomor kontak Customer',
        'Detail barang',
        'Berat dan dimensi barang',
        'Lokasi pickup dan drop-off',
        'Status pengiriman',
        'Bukti pengiriman',
        'Informasi pendapatan',
        'Lainnya',
    ];

    public const INTERVIEW_CONSENT = [
        'ya' => 'Ya',
        'tidak' => 'Tidak',
    ];

    /**
     * Gabungan seluruh pilihan, dipakai berulang di Blade.
     */
    public static function options(): array
    {
        return [
            'roles' => self::ROLES,
            'ageRanges' => self::AGE_RANGES,
            'services' => self::SERVICES,
            'usageFrequencies' => self::USAGE_FREQUENCIES,
            'difficultyLevels' => self::DIFFICULTY_LEVELS,
            'customerActivities' => self::CUSTOMER_ACTIVITIES,
            'customerProblemAreas' => self::CUSTOMER_PROBLEM_AREAS,
            'customerImportantInfo' => self::CUSTOMER_IMPORTANT_INFO,
            'courierStatuses' => self::COURIER_STATUSES,
            'courierApps' => self::COURIER_APPS,
            'courierActivities' => self::COURIER_ACTIVITIES,
            'courierProblemAreas' => self::COURIER_PROBLEM_AREAS,
            'courierImportantInfo' => self::COURIER_IMPORTANT_INFO,
            'interviewConsent' => self::INTERVIEW_CONSENT,
            'textLimit' => self::TEXT_LIMIT,
        ];
    }
}
