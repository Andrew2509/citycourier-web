<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\Chat\Support\PhoneNumberNormalizer;
use App\Modules\Payment\Services\GatewayCredentialsResolver;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class WhatsAppCloudClient
{
    /**
     * @return array<string, mixed>
     */
    public function credentials(): array
    {
        return app(GatewayCredentialsResolver::class)->whatsappCloud();
    }

    public function isConfigured(): bool
    {
        $creds = $this->credentials();

        return (bool) ($creds['enabled'] ?? false)
            && ! empty($creds['phone_number_id'])
            && ! empty($creds['access_token']);
    }

    /**
     * @return array{ok: bool, wa_message_id: ?string, http_status: int, error_code: ?string, error_message: ?string, raw: array<string, mixed>, transient: bool}
     */
    public function sendText(string $toE164, string $body): array
    {
        return $this->sendPayload([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $toE164,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $body,
            ],
        ]);
    }

    /**
     * @param  array<int, string>  $bodyParameters
     * @return array{ok: bool, wa_message_id: ?string, http_status: int, error_code: ?string, error_message: ?string, raw: array<string, mixed>, transient: bool}
     */
    public function sendUtilityTemplate(
        string $toE164,
        string $templateName,
        string $languageCode,
        array $bodyParameters = []
    ): array {
        $components = [];
        if ($bodyParameters !== []) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(
                    fn (string $value): array => ['type' => 'text', 'text' => $value],
                    $bodyParameters
                ),
            ];
        }

        return $this->sendPayload([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $toE164,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $languageCode],
                'components' => $components,
            ],
        ]);
    }

    /**
     * Uploads media binary directly to Meta Cloud API to keep storage private.
     *
     * @return array{ok: bool, media_id: ?string, http_status: int, error_code: ?string, error_message: ?string}
     */
    public function uploadMedia(string $binaryContents, string $mimeType, string $filename): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'media_id' => null,
                'http_status' => 0,
                'error_code' => 'WHATSAPP_DISABLED',
                'error_message' => 'WhatsApp Cloud API tidak aktif.',
            ];
        }

        $creds = $this->credentials();

        $url = sprintf(
            '%s/%s/%s/media',
            $creds['base_url'],
            $creds['api_version'],
            $creds['phone_number_id']
        );

        $response = Http::withToken((string) $creds['access_token'])
            ->timeout((int) ($creds['timeout_seconds'] ?? 15))
            ->attach('file', $binaryContents, $filename, ['Content-Type' => $mimeType])
            ->post($url, ['messaging_product' => 'whatsapp']);

        $json = $response->json();
        $raw = is_array($json) ? $json : [];

        if ($response->successful() && ! empty($raw['id'])) {
            return [
                'ok' => true,
                'media_id' => (string) $raw['id'],
                'http_status' => $response->status(),
                'error_code' => null,
                'error_message' => null,
            ];
        }

        $error = is_array($raw['error'] ?? null) ? $raw['error'] : [];

        return [
            'ok' => false,
            'media_id' => null,
            'http_status' => $response->status(),
            'error_code' => isset($error['code']) ? (string) $error['code'] : (string) $response->status(),
            'error_message' => (string) ($error['message'] ?? 'Gagal upload media ke Meta.'),
        ];
    }

    /**
     * @return array{ok: bool, wa_message_id: ?string, http_status: int, error_code: ?string, error_message: ?string, raw: array<string, mixed>, transient: bool}
     */
    public function sendImageByMediaId(string $toE164, string $mediaId, ?string $caption = null): array
    {
        $image = ['id' => $mediaId];
        if ($caption !== null && trim($caption) !== '') {
            $image['caption'] = $caption;
        }

        return $this->sendPayload([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $toE164,
            'type' => 'image',
            'image' => $image,
        ]);
    }

    /**
     * Probes Meta Graph API phone number node to verify Access Token & Phone Number ID.
     * Accepts unsaved overrides from Admin UI so credentials can be tested before saving.
     *
     * @param  array<string, mixed>  $overrides
     * @return array{success: bool, status_code: int, message: string, data?: array<string, mixed>}
     */
    public function testConnection(array $overrides = []): array
    {
        $creds = $this->credentials();

        $baseUrl = rtrim((string) ($overrides['base_url'] ?? $creds['base_url'] ?: 'https://graph.facebook.com'), '/');
        $apiVersion = trim((string) ($overrides['api_version'] ?? $creds['api_version'] ?: 'v21.0'));
        $phoneNumberId = trim((string) ($overrides['phone_number_id'] ?? $creds['phone_number_id']));
        $accessToken = trim((string) ($overrides['access_token'] ?? $creds['access_token']));
        $timeout = max(3, min(60, (int) ($overrides['timeout_seconds'] ?? ($creds['timeout_seconds'] ?? 15))));

        if ($phoneNumberId === '' || $accessToken === '') {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'Phone Number ID dan Permanent Access Token wajib diisi sebelum menguji koneksi Meta Graph API.',
            ];
        }

        try {
            $url = sprintf('%s/%s/%s', $baseUrl, $apiVersion, $phoneNumberId);
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout($timeout)
                ->get($url, [
                    'fields' => 'id,display_phone_number,verified_name,quality_rating',
                ]);

            $json = $response->json();
            $raw = is_array($json) ? $json : [];

            if ($response->successful() && ! empty($raw['id'])) {
                $displayPhone = (string) ($raw['display_phone_number'] ?? '-');
                $verifiedName = (string) ($raw['verified_name'] ?? 'Terverifikasi');
                $quality = (string) ($raw['quality_rating'] ?? '-');

                return [
                    'success' => true,
                    'status_code' => 200,
                    'message' => "Koneksi Meta Cloud API Berhasil! Nomor: {$displayPhone} ({$verifiedName}), Kualitas: {$quality}.",
                    'data' => $raw,
                ];
            }

            $error = is_array($raw['error'] ?? null) ? $raw['error'] : [];
            $errorMsg = (string) ($error['message'] ?? ('Meta Graph API merespons HTTP ' . $response->status()));
            $errorCode = isset($error['code']) ? ' [Code ' . $error['code'] . ']' : '';

            return [
                'success' => false,
                'status_code' => $response->status() >= 400 ? 400 : 400,
                'message' => 'Koneksi Meta Gagal' . $errorCode . ': ' . $errorMsg,
                'data' => $raw,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'status_code' => 500,
                'message' => 'Gagal menghubungi Meta Graph API: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Sends a diagnostic WhatsApp message directly from Admin Providers UI
     * so operators can verify sandbox allowlists, template approvals, or 24h windows.
     *
     * @param  array<string, mixed>  $overrides
     * @return array{success: bool, status_code: int, message: string, data?: array<string, mixed>}
     */
    public function sendTestMessage(string $toRaw, string $mode = 'hello_world', array $overrides = []): array
    {
        $toE164 = PhoneNumberNormalizer::toE164($toRaw);
        if ($toE164 === null) {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'Format nomor HP tujuan tidak valid. Gunakan format 08xxxxxxxxxx atau 628xxxxxxxxxx.',
            ];
        }

        $creds = $this->credentials();
        $baseUrl = rtrim((string) ($overrides['base_url'] ?? $creds['base_url'] ?: 'https://graph.facebook.com'), '/');
        $apiVersion = trim((string) ($overrides['api_version'] ?? $creds['api_version'] ?: 'v21.0'));
        $phoneNumberId = trim((string) ($overrides['phone_number_id'] ?? $creds['phone_number_id']));
        $accessToken = trim((string) ($overrides['access_token'] ?? $creds['access_token']));
        $templateName = trim((string) ($overrides['utility_template_name'] ?? $creds['utility_template_name'] ?: 'city_courier_chat_notification'));
        $templateLang = trim((string) ($overrides['utility_template_language'] ?? $creds['utility_template_language'] ?: 'id'));
        $timeout = max(3, min(60, (int) ($overrides['timeout_seconds'] ?? ($creds['timeout_seconds'] ?? 15))));

        if ($phoneNumberId === '' || $accessToken === '') {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'Phone Number ID dan Access Token wajib diisi sebelum mengirim pesan tes WhatsApp.',
            ];
        }

        $payload = match ($mode) {
            'utility_template' => [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $toE164,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => $templateLang],
                    'components' => [[
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => 'CC-TEST-001'],
                            ['type' => 'text', 'text' => 'Admin City Courier'],
                            ['type' => 'text', 'text' => 'Tes pengiriman notifikasi chat order via template resmi.'],
                        ],
                    ]],
                ],
            ],
            'text' => [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $toE164,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => '[City Courier Test] Tes pengiriman pesan teks langsung dari Admin Panel.',
                ],
            ],
            default => [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $toE164,
                'type' => 'template',
                'template' => [
                    'name' => 'hello_world',
                    'language' => ['code' => 'en_US'],
                ],
            ],
        };

        try {
            $url = sprintf('%s/%s/%s/messages', $baseUrl, $apiVersion, $phoneNumberId);
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout($timeout)
                ->post($url, $payload);

            $json = $response->json();
            $raw = is_array($json) ? $json : [];

            if ($response->successful() && ! empty($raw['messages'][0]['id'])) {
                $wamid = (string) $raw['messages'][0]['id'];

                return [
                    'success' => true,
                    'status_code' => 200,
                    'message' => "Pesan tes ({$mode}) berhasil diterima Meta untuk tujuan {$toE164}! ID: {$wamid}",
                    'data' => $raw,
                ];
            }

            $error = is_array($raw['error'] ?? null) ? $raw['error'] : [];
            $code = isset($error['code']) ? (string) $error['code'] : (string) $response->status();
            $rawMessage = (string) ($error['message'] ?? ('Meta Graph API merespons HTTP ' . $response->status()));

            $hint = match ($code) {
                '131030' => "Nomor {$toE164} belum ditambahkan di daftar nomor uji coba (Manage phone number list) pada Meta Developers -> WhatsApp -> API Setup.",
                '131037' => "Display Name nomor pengirim belum disetujui Meta (error 131037). Buka Meta Business Suite -> WhatsApp -> Settings -> Profile, cek status Display Name: bila PENDING, tunggu persetujuan Meta; bila DECLINED, ajukan ulang nama dengan ejaan yang benar. Pengiriman tetap ditolak sampai Display Name berstatus APPROVED.",
                '132001' => "Template '{$templateName}' ({$templateLang}) belum dibuat atau belum disetujui (Approved) di Pengelola WhatsApp Meta.",
                '131047' => "Jendela balasan 24 jam untuk {$toE164} belum terbuka. Gunakan mode template (hello_world / utility_template), atau balas pesan WA dari HP tersebut dulu.",
                default => $rawMessage,
            };

            return [
                'success' => false,
                'status_code' => 400,
                'message' => "Gagal Kirim Tes WA [Code {$code}]: {$hint}",
                'data' => $raw,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'status_code' => 500,
                'message' => 'Gagal menghubungi Meta Graph API: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, wa_message_id: ?string, http_status: int, error_code: ?string, error_message: ?string, raw: array<string, mixed>, transient: bool}
     */
    private function sendPayload(array $payload): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'wa_message_id' => null,
                'http_status' => 0,
                'error_code' => 'WHATSAPP_DISABLED',
                'error_message' => 'WhatsApp Cloud API tidak dikonfigurasi.',
                'raw' => [],
                'transient' => false,
            ];
        }

        $creds = $this->credentials();

        $url = sprintf(
            '%s/%s/%s/messages',
            $creds['base_url'],
            $creds['api_version'],
            $creds['phone_number_id']
        );

        /** @var Response $response */
        $response = Http::withToken((string) $creds['access_token'])
            ->acceptJson()
            ->timeout((int) ($creds['timeout_seconds'] ?? 15))
            ->post($url, $payload);

        $json = $response->json();
        $raw = is_array($json) ? $json : [];

        if ($response->successful()) {
            $waMessageId = $raw['messages'][0]['id'] ?? null;

            return [
                'ok' => $waMessageId !== null,
                'wa_message_id' => $waMessageId !== null ? (string) $waMessageId : null,
                'http_status' => $response->status(),
                'error_code' => null,
                'error_message' => null,
                'raw' => $raw,
                'transient' => false,
            ];
        }

        $error = is_array($raw['error'] ?? null) ? $raw['error'] : [];
        $status = $response->status();

        return [
            'ok' => false,
            'wa_message_id' => null,
            'http_status' => $status,
            'error_code' => isset($error['code']) ? (string) $error['code'] : (string) $status,
            'error_message' => (string) ($error['message'] ?? 'Meta Cloud API request failed.'),
            'raw' => $raw,
            'transient' => $status === 429 || $status >= 500,
        ];
    }
}
