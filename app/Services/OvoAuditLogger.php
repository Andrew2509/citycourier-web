<?php

namespace App\Services;

use App\Models\OvoApiLog;
use Illuminate\Support\Facades\Log;

/**
 * OvoAuditLogger — pencatat audit untuk seluruh panggilan API OVO SNAP.
 *
 * Aturan keamanan:
 * - SECRET, PRIVATE KEY, ACCESS TOKEN, SIGNATURE, PIN, OTP selalu di-redact
 *   (tidak pernah tersimpan).
 * - Jika penulisan log DB gagal, tidak menggagalkan transaksi OVO —
 *   hanya fallback ke log aplikasi (Laravel log) tanpa payload sensitif.
 */
class OvoAuditLogger
{
    /** Kunci payload yang di-redact penuh. */
    private const REDACT_KEYS = [
        'signature', 'x-signature', 'authorization', 'access_token', 'accesstoken',
        'client_secret', 'secret', 'password', 'pin', 'otp', 'private_key', 'privatekey',
        'public_key', 'publickey', 'client_key', 'additionalinfo', 'token', 'key', 'ut',
    ];

    /** Panjang maksimal payload response tersimpan. */
    private const MAX_PAYLOAD_LENGTH = 6000;

    public static function record(
        string $action,
        string $endpoint,
        array $rawRequest,
        $rawResponse,
        ?int $httpStatus,
        ?string $responseCode,
        bool $success,
        ?float $durationMs = null
    ): void {
        try {
            OvoApiLog::create([
                'action'           => $action,
                'endpoint'         => $endpoint,
                'http_status'      => $httpStatus,
                'response_code'    => $responseCode,
                'success'          => $success,
                'request_payload'  => json_encode(self::redact($rawRequest), JSON_UNESCAPED_UNICODE),
                'response_payload' => mb_substr(json_encode(self::redact($rawResponse), JSON_UNESCAPED_UNICODE) ?? '', 0, self::MAX_PAYLOAD_LENGTH),
                'duration_ms'      => $durationMs !== null ? (int) round($durationMs) : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[OvoAuditLogger] Gagal menulis log audit OVO', [
                'action' => $action,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * Redaksi nilai pada kunci sensitif, baik di level atas maupun bersarang.
     */
    public static function redact($payload, int $depth = 0)
    {
        if ($depth > 6) {
            return '[REDACTED-DEEP]';
        }

        if (!is_array($payload)) {
            return $payload;
        }

        $result = [];
        foreach ($payload as $key => $value) {
            $keyLower = strtolower((string) $key);

            if (in_array($keyLower, self::REDACT_KEYS, true)) {
                $result[$key] = '[REDACTED]';
                continue;
            }

            $result[$key] = is_array($value)
                ? self::redact($value, $depth + 1)
                : $value;
        }

        return $result;
    }

    /**
     * Redaksi deretan header HTTP — hanya nama disimpan, nilai berpotensi
     * berisi token/signature sehingga tidak disimpan.
     *
     * @param array $headers array nama => nilai
     */
    public static function redactHeaders(array $headers): array
    {
        return array_map(static fn () => '[REDACTED]', array_keys($headers));
    }
}
