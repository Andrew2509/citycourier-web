<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Modules\Chat\Models\WhatsappMessageLog;
use App\Modules\Payment\Services\GatewayCredentialsResolver;
use App\Modules\WhatsApp\Jobs\ProcessWhatsAppWebhookJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class WebhookController extends Controller
{
    /**
     * Handles Meta webhook verification handshake (hub.mode, hub.verify_token, hub.challenge).
     */
    public function verify(Request $request): Response
    {
        $mode = (string) ($request->query('hub_mode') ?? $request->query('hub.mode') ?? '');
        $token = (string) ($request->query('hub_verify_token') ?? $request->query('hub.verify_token') ?? '');
        $challenge = (string) ($request->query('hub_challenge') ?? $request->query('hub.challenge') ?? '');

        $creds = app(GatewayCredentialsResolver::class)->whatsappCloud();
        $expectedToken = (string) ($creds['verify_token'] ?? '');

        if (
            $mode === 'subscribe'
            && $expectedToken !== ''
            && hash_equals($expectedToken, $token)
        ) {
            return response($challenge, 200, ['Content-Type' => 'text/plain']);
        }

        return response('Forbidden', 403);
    }

    /**
     * Verifies X-Hub-Signature-256 HMAC, deduplicates by payload hash, logs, and queues processing.
     */
    public function handle(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $creds = app(GatewayCredentialsResolver::class)->whatsappCloud();
        $appSecret = (string) ($creds['app_secret'] ?? '');
        $signatureHeader = (string) $request->header('X-Hub-Signature-256', '');

        if (! $this->isValidSignature($rawBody, $signatureHeader, $appSecret)) {
            WhatsappMessageLog::query()->firstOrCreate(
                ['event_hash' => hash('sha256', 'invalid:' . $rawBody . ':' . $signatureHeader)],
                [
                    'direction' => WhatsappMessageLog::DIRECTION_INBOUND,
                    'signature_valid' => false,
                    'http_status' => 403,
                    'processing_status' => WhatsappMessageLog::PROCESS_IGNORED,
                    'payload' => $this->decodeJson($rawBody),
                    'error_message' => 'Invalid X-Hub-Signature-256.',
                ]
            );

            return response()->json(['success' => false, 'message' => 'Invalid webhook signature.'], 403);
        }

        $eventHash = hash('sha256', $rawBody);
        $existing = WhatsappMessageLog::query()->where('event_hash', $eventHash)->first();

        if ($existing !== null) {
            return response()->json(['received' => true, 'duplicate' => true], 200);
        }

        $payload = $this->decodeJson($rawBody);
        $log = WhatsappMessageLog::create([
            'direction' => $this->detectDirection($payload),
            'event_hash' => $eventHash,
            'signature_valid' => true,
            'http_status' => 200,
            'processing_status' => WhatsappMessageLog::PROCESS_RECEIVED,
            'payload' => $payload,
        ]);

        ProcessWhatsAppWebhookJob::dispatch($log->id);

        return response()->json(['received' => true, 'duplicate' => false], 200);
    }

    private function isValidSignature(string $rawBody, string $header, string $appSecret): bool
    {
        if ($appSecret === '' || ! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $appSecret);

        return hash_equals($expected, $header);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $rawBody): array
    {
        $decoded = json_decode($rawBody, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function detectDirection(array $payload): string
    {
        $value = $payload['entry'][0]['changes'][0]['value'] ?? [];
        if (! empty($value['statuses']) && empty($value['messages'])) {
            return WhatsappMessageLog::DIRECTION_STATUS_WEBHOOK;
        }

        return WhatsappMessageLog::DIRECTION_INBOUND;
    }
}
