<?php

namespace App\Modules\Chat\Support;

/**
 * Normalizes phone numbers to canonical E.164 digit-only form (without leading '+')
 * required by Meta WhatsApp Business Cloud API (e.g. 081234567890 -> 6281234567890).
 */
class PhoneNumberNormalizer
{
    public static function toE164(?string $raw, ?string $defaultCountryCode = null): ?string
    {
        if ($raw === null) {
            return null;
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        $hasPlusPrefix = str_starts_with($trimmed, '+');
        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if ($digits === '') {
            return null;
        }

        $country = preg_replace(
            '/\D+/',
            '',
            $defaultCountryCode ?? (string) config('whatsapp.default_country_code', '62')
        ) ?: '62';

        if (! $hasPlusPrefix) {
            if (str_starts_with($digits, '00')) {
                $digits = substr($digits, 2);
            } elseif (str_starts_with($digits, '0')) {
                $digits = $country . substr($digits, 1);
            } elseif (preg_match('/^8\d{7,12}$/', $digits) === 1 && $country === '62') {
                // Local Indonesian mobile without leading zero or country code
                $digits = '62' . $digits;
            }
        }

        $length = strlen($digits);
        if ($length < 8 || $length > 15) {
            return null;
        }

        return $digits;
    }
}
