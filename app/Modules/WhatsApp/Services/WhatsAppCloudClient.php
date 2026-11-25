<?php

namespace App\Modules\WhatsApp\Services;

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
