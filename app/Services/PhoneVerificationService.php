<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Penerbit dan validator kode OTP yang dipakai bersama oleh login nomor
 * telepon dan verifikasi nomor pada halaman Lengkapi Data Diri.
 *
 * Aturan keamanan:
 * - kode disimpan sebagai hash, bukan teks biasa;
 * - berlaku maksimal 5 menit dan hanya bisa dipakai sekali;
 * - ada cooldown 60 detik sebelum meminta kode baru;
 * - maksimal 5 percobaan salah per kode.
 */
class PhoneVerificationService
{
    public const PURPOSE_LOGIN = 'login';

    public const PURPOSE_PROFILE = 'profile';

    public const EXPIRY_MINUTES = 5;

    public const COOLDOWN_SECONDS = 60;

    public const MAX_ATTEMPTS = 5;

    public const STATUS_SENT = 'sent';

    public const STATUS_COOLDOWN = 'cooldown';

    public const STATUS_SEND_FAILED = 'send_failed';

    public const STATUS_OK = 'ok';

    public const STATUS_INVALID = 'invalid';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_TOO_MANY = 'too_many';

    public function __construct(private readonly WhatsAppManager $whatsapp)
    {
    }

    /**
     * Kirim kode OTP baru.
     *
     * @return array{status: string, retry_after?: int, otp?: string, provider_message?: string}
     */
    public function send(
        string $purpose,
        string $normalizedPhone,
        int $digits = 4,
        ?int $userId = null,
    ): array {
        $cooldownKey = $this->cooldownKey($purpose, $normalizedPhone, $userId);
        $cooldownUntil = (int) Cache::get($cooldownKey, 0);
        $now = now()->getTimestamp();

        if ($cooldownUntil > $now) {
            return [
                'status' => self::STATUS_COOLDOWN,
                'retry_after' => $cooldownUntil - $now,
            ];
        }

        $min = $digits === 6 ? 100000 : 1000;
        $max = $digits === 6 ? 999999 : 9999;
        $otp = (string) random_int($min, $max);

        $result = $this->whatsapp->sendOtp($normalizedPhone, $otp);

        if (! ($result['success'] ?? false)) {
            return [
                'status' => self::STATUS_SEND_FAILED,
                'provider_message' => $result['message'] ?? null,
            ];
        }

        $expiresAt = now()->addMinutes(self::EXPIRY_MINUTES)->getTimestamp();

        Cache::put(
            $this->recordKey($purpose, $normalizedPhone, $userId),
            [
                'hash' => Hash::make($otp),
                'attempts' => 0,
                'expires_at' => $expiresAt,
            ],
            now()->addMinutes(self::EXPIRY_MINUTES),
        );

        // Penanda agar backend bisa membedakan "kedaluwarsa" dari "tidak
        // pernah diminta". Tidak menyimpan kode apa pun.
        Cache::put(
            $this->expiredMarkerKey($purpose, $normalizedPhone, $userId),
            true,
            now()->addMinutes(self::EXPIRY_MINUTES * 3),
        );

        Cache::put($cooldownKey, $now + self::COOLDOWN_SECONDS, now()->addSeconds(self::COOLDOWN_SECONDS));

        return [
            'status' => self::STATUS_SENT,
            'otp' => $otp,
            'retry_after' => self::COOLDOWN_SECONDS,
        ];
    }

    /**
     * Validasi kode OTP.
     *
     * @return array{status: string, attempts_left?: int}
     */
    public function verify(
        string $purpose,
        string $normalizedPhone,
        string $otp,
        ?int $userId = null,
    ): array {
        $recordKey = $this->recordKey($purpose, $normalizedPhone, $userId);
        $record = Cache::get($recordKey);

        if (! is_array($record) || ! isset($record['hash'], $record['expires_at'])) {
            return [
                'status' => Cache::has($this->expiredMarkerKey($purpose, $normalizedPhone, $userId))
                    ? self::STATUS_EXPIRED
                    : self::STATUS_INVALID,
            ];
        }

        $now = now()->getTimestamp();

        if ((int) $record['expires_at'] <= $now) {
            Cache::forget($recordKey);

            return ['status' => self::STATUS_EXPIRED];
        }

        if (! Hash::check($otp, (string) $record['hash'])) {
            $attempts = (int) ($record['attempts'] ?? 0) + 1;

            if ($attempts >= self::MAX_ATTEMPTS) {
                Cache::forget($recordKey);
                Cache::forget($this->expiredMarkerKey($purpose, $normalizedPhone, $userId));

                return ['status' => self::STATUS_TOO_MANY, 'attempts_left' => 0];
            }

            $record['attempts'] = $attempts;
            $remainingTtl = max(1, (int) $record['expires_at'] - $now);
            Cache::put($recordKey, $record, now()->addSeconds($remainingTtl));

            return [
                'status' => self::STATUS_INVALID,
                'attempts_left' => self::MAX_ATTEMPTS - $attempts,
            ];
        }

        // Satu kode hanya boleh berhasil sekali.
        Cache::forget($recordKey);
        Cache::forget($this->expiredMarkerKey($purpose, $normalizedPhone, $userId));

        return ['status' => self::STATUS_OK];
    }

    private function recordKey(string $purpose, string $phone, ?int $userId): string
    {
        return 'otp:' . $purpose . ':' . ($userId ?? 'guest') . ':' . $phone;
    }

    private function expiredMarkerKey(string $purpose, string $phone, ?int $userId): string
    {
        return 'otp:expired:' . $purpose . ':' . ($userId ?? 'guest') . ':' . $phone;
    }

    private function cooldownKey(string $purpose, string $phone, ?int $userId): string
    {
        return 'otp:cooldown:' . $purpose . ':' . ($userId ?? 'guest') . ':' . $phone;
    }
}
