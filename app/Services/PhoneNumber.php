<?php

namespace App\Services;

/**
 * Normalisasi nomor telepon Indonesia untuk kebutuhan autentikasi.
 *
 * Format yang diterima: `0812…`, `62812…`, `+62 812-…`, `812…`, dan
 * `0062812…`. Semua dinormalisasi ke format lokal `08…` agar pencocokan
 * dengan kolom `users.phone` konsisten.
 */
class PhoneNumber
{
    /** Panjang nomor seluler Indonesia: 10–13 digit (termasuk awalan 08). */
    private const LOCAL_PATTERN = '/^08[0-9]{8,11}$/';

    /**
     * Kembalikan nomor dalam format lokal `08…`, atau null bila bukan nomor
     * seluler Indonesia yang valid.
     */
    public static function normalize(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        if ($digits === '') {
            return null;
        }

        // 0062… (prefiks internasional ganda)
        if (str_starts_with($digits, '0062')) {
            $digits = substr($digits, 4);
        }
        // +62… / 62…
        elseif (str_starts_with($digits, '62')) {
            $digits = substr($digits, 2);
        }
        // 8… tanpa awalan 0
        elseif (str_starts_with($digits, '8')) {
            $digits = '0' . $digits;
        }

        // Setelah prefiks internasional, nomor lokal harus diawali 8.
        if (str_starts_with($digits, '8')) {
            $digits = '0' . $digits;
        }

        if (preg_match(self::LOCAL_PATTERN, $digits) !== 1) {
            return null;
        }

        return $digits;
    }

    /**
     * Variasi penulisan yang mungkin tersimpan di database untuk nomor yang
     * sama. Dipakai untuk query `whereIn('phone', ...)`.
     *
     * @return array<int, string>
     */
    public static function variants(?string $value): array
    {
        $local = self::normalize($value);
        if ($local === null) {
            return [];
        }

        $international = '62' . substr($local, 1);

        return array_values(array_unique([
            $local,             // 081234567890
            $international,     // 6281234567890
            '+' . $international, // +6281234567890
        ]));
    }
}
