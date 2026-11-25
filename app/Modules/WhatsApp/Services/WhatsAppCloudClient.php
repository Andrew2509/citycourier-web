<?php

namespace App\Modules\WhatsApp\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class WhatsAppCloudClient
{
    public function isConfigured(): bool
    {
        return (bool) config('whatsapp.enabled', false)
            && ! empty(config('whatsapp.phone_number_id'))
            && ! empty(config('whatsapp.access_token'));
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

        $url = sprintf(
            '%s/%s/%s/media',
            config('whatsapp.base_url'),
            config('whatsapp.api_version'),
            config('whatsapp.phone_number_id')
        );

        $response = Http::withToken((string) config('whatsapp.access_token'))
            ->timeout((int) config('whatsapp.timeout_seconds', 15))
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

        $url = sprintf(
            '%s/%s/%s/messages',
            config('whatsapp.base_url'),
            config('whatsapp.api_version'),
            config('whatsapp.phone_number_id')
        );

        /** @var Response $response */
        $response = Http::withToken((string) config('whatsapp.access_token'))
            ->acceptJson()
            ->timeout((int) config('whatsapp.timeout_seconds', 15))
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
