<?php

namespace App\Services;

/**
 * Menentukan identitas "buyer" yang dikirim ke payment gateway (iPaymu).
 *
 * Latar belakang: iPaymu menolak transaksi production dengan pesan
 * "Suspicious buyer" bila data pembeli terlihat palsu. Contoh data yang
 * memicu penolakan:
 * - email dengan domain reserved / tidak routable (`@citycourier.local`,
 *   `@example.com`, `@test.com`) — akun hasil login OTP sebelumnya memakai
 *   pola `<phone>@citycourier.local`;
 * - nama generik berisi angka (`User 1234`) atau kata "Test"/"Customer";
 * - nomor telepon kosong / berisi karakter non-digit.
 *
 * Kelas ini merapikan data tersebut menjadi identitas yang wajar. Nomor
 * telepon dinormalisasi ke format lokal `08…`, dan bila email pengguna tidak
 * layak dipakai, dibuat alamat valid pada domain merchant (bukan domain
 * reserved) supaya iPaymu tidak menandainya sebagai pembeli mencurigakan.
 */
class BuyerIdentity
{
    /** Domain email reserved / tidak bisa menerima email. */
    private const RESERVED_DOMAINS = [
        'example.com', 'example.org', 'example.net',
        'test.com', 'test.local', 'localhost', 'localhost.localdomain',
        'citycourier.local', 'invalid', 'local',
    ];

    /** TLD yang tidak boleh dipakai (RFC 2606 / RFC 6762). */
    private const RESERVED_TLDS = [
        'local', 'localhost', 'test', 'example', 'invalid', 'localdomain',
    ];

    private const GENERIC_NAMES = [
        'user', 'test', 'tester', 'customer', 'buyer', 'pelanggan',
        'admin', 'guest', 'test account', 'nama', 'name',
    ];

    /**
     * Nama dianggap placeholder bila kosong, terlalu pendek, mengandung angka,
     * atau persis kata generik.
     */
    public static function isPlaceholderName(?string $name): bool
    {
        $name = trim((string) $name);

        if ($name === '' || mb_strlen($name) < 2) {
            return true;
        }
        // "User 1234", "Tester 01", nama berisi digit lain.
        if (preg_match('/\d{2,}/', $name) === 1) {
            return true;
        }
        if (in_array(mb_strtolower($name), self::GENERIC_NAMES, true)) {
            return true;
        }

        return false;
    }

    /**
     * Pilih nama pertama yang bukan placeholder. Kandidat diurutkan dari yang
     * paling spesifik (data shipment) ke paling umum (nama akun).
     */
    public static function name(?string ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if (!self::isPlaceholderName($candidate)) {
                return $candidate;
            }
        }

        return 'Pelanggan City Courier';
    }

    /**
     * Normalisasi nomor telepon Indonesia ke format lokal `08…`.
     * `+62 812-3456-7890` → `081234567890`.
     *
     * Kandidat diperiksa berurutan; kandidat pertama yang berisi digit dipakai.
     */
    public static function phone(?string ...$candidates): string
    {
        $digits = '';
        foreach ($candidates as $candidate) {
            $digits = preg_replace('/\D+/', '', (string) $candidate) ?? '';
            if ($digits !== '') {
                break;
            }
        }
        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '62')) {
            $digits = '0' . substr($digits, 2);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '0' . $digits;
        }

        return $digits;
    }

    /** Email benar-benar bisa dipakai (format valid + domain tidak reserved). */
    public static function isUsableEmail(?string $email): bool
    {
        $email = trim((string) $email);
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        $at = strrpos($email, '@');
        if ($at === false) {
            return false;
        }
        $domain = strtolower(substr($email, $at + 1));

        if (!str_contains($domain, '.')) {
            return false;
        }
        if (in_array($domain, self::RESERVED_DOMAINS, true)) {
            return false;
        }

        $tld = substr($domain, strrpos($domain, '.') + 1);
        return !in_array($tld, self::RESERVED_TLDS, true);
    }

    /**
     * Email buyer: pakai kandidat valid pertama; bila tidak ada, buat alamat
     * valid pada domain merchant berdasarkan nomor telepon.
     */
    public static function email(?string $preferred, ?string $fallback = null, string $phone = ''): string
    {
        foreach ([$preferred, $fallback] as $candidate) {
            if (self::isUsableEmail($candidate)) {
                return trim((string) $candidate);
            }
        }

        $local = 'pelanggan' . ($phone !== '' ? '.' . $phone : '');

        return $local . '@' . self::emailDomain();
    }

    /** Domain email fallback: config khusus, atau host dari APP_URL. */
    public static function emailDomain(): string
    {
        $configured = trim((string) config('ipaymu.buyer_email_domain', ''));
        if ($configured !== '') {
            return ltrim($configured, '@');
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return ($host && $host !== 'localhost') ? $host : 'citycourier.app';
    }
}
