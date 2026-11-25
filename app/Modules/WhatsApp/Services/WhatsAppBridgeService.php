<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\Message;
use App\Modules\Chat\Models\WhatsappContact;
use App\Modules\Chat\Models\WhatsappMessageLog;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class WhatsAppBridgeService
{
    public function __construct(
        private readonly WhatsAppCloudClient $client
    ) {
    }

    /**
     * Bridges an internal chat message to an opted-in recipient's WhatsApp via Meta Cloud API.
     * Obeys Meta's 24h customer service window (free text/media inside window, approved template outside).
     */
    public function dispatchOutbound(Conversation $conversation, Message $message, int $attempt = 1): void
    {
        if (
            ! $this->client->isConfigured()
            || ! $conversation->wa_bridged_enabled
            || $conversation->status !== Conversation::STATUS_ACTIVE
            || $message->channel !== Message::CHANNEL_INTERNAL
            || $message->sender_role === Message::ROLE_SYSTEM
            || $message->wa_message_id !== null
        ) {
            return;
        }

        $recipientUserId = $message->sender_role === Message::ROLE_CUSTOMER
            ? $conversation->courier_user_id
            : $conversation->customer_user_id;

        if ($recipientUserId === null) {
            return;
        }

        $contact = WhatsappContact::query()->where('user_id', $recipientUserId)->first();
        if ($contact === null || ! $contact->isOptedIn() || empty($contact->wa_phone_e164)) {
            return;
        }

        // Remember latest bridged order conversation so direct WA replies route accurately
        if ($contact->active_conversation_id !== $conversation->id) {
            $contact->active_conversation_id = $conversation->id;
            $contact->save();
        }

        $message->loadMissing('sender');
        $senderLabel = $message->sender?->name
            ?? ($message->sender_role === Message::ROLE_COURIER ? 'Kurir City Courier' : 'Pelanggan');
        $orderRef = $conversation->order_number ?: ('#' . $conversation->order_id);

        if ($contact->isWindowOpen()) {
            $result = $this->sendInWindowMessage($contact->wa_phone_e164, $orderRef, $senderLabel, $message);
        } else {
            $creds = $this->client->credentials();
            $result = $this->client->sendUtilityTemplate(
                toE164: $contact->wa_phone_e164,
                templateName: (string) ($creds['utility_template_name'] ?? config('whatsapp.utility_template_name')),
                languageCode: (string) ($creds['utility_template_language'] ?? config('whatsapp.utility_template_language', 'id')),
                bodyParameters: [
                    $orderRef,
                    $senderLabel,
                    Str::limit((string) ($message->body ?: $message->previewText()), 140),
                ]
            );
        }

        $this->recordOutboundLog($conversation, $message, $attempt, $result);

        if ($result['ok'] && $result['wa_message_id'] !== null) {
            $message->forceFill([
                'wa_message_id' => $result['wa_message_id'],
                'wa_status' => 'accepted',
                'wa_error_code' => null,
                'wa_error_message' => null,
            ])->save();

            return;
        }

        $message->forceFill([
            'wa_status' => 'failed',
            'wa_error_code' => $result['error_code'],
            'wa_error_message' => Str::limit((string) ($result['error_message'] ?? ''), 250),
        ])->save();

        if ($result['transient']) {
            throw new RuntimeException(
                sprintf('Transient Meta API error (%s): %s', (string) $result['error_code'], (string) $result['error_message'])
            );
        }
    }

    /**
     * @return array{ok: bool, wa_message_id: ?string, http_status: int, error_code: ?string, error_message: ?string, raw: array<string, mixed>, transient: bool}
     */
    private function sendInWindowMessage(
        string $toE164,
        string $orderRef,
        string $senderLabel,
        Message $message
    ): array {
        $caption = sprintf('[Order %s] %s: %s', $orderRef, $senderLabel, trim((string) ($message->body ?? '')));

        if ($message->message_type === Message::TYPE_IMAGE && $message->media_path !== null) {
            $disk = (string) config('chat.media_disk', 'local');
            if (Storage::disk($disk)->exists($message->media_path)) {
                $contents = Storage::disk($disk)->get($message->media_path);
                $upload = $this->client->uploadMedia(
                    $contents,
                    $message->media_mime ?? 'image/jpeg',
                    basename($message->media_path)
                );

                if ($upload['ok'] && $upload['media_id'] !== null) {
                    return $this->client->sendImageByMediaId($toE164, $upload['media_id'], $caption);
                }

                return [
                    'ok' => false,
                    'wa_message_id' => null,
                    'http_status' => $upload['http_status'],
                    'error_code' => $upload['error_code'],
                    'error_message' => $upload['error_message'],
                    'raw' => [],
                    'transient' => $upload['http_status'] === 429 || $upload['http_status'] >= 500,
                ];
            }
        }

        return $this->client->sendText($toE164, $caption);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function recordOutboundLog(
        Conversation $conversation,
        Message $message,
        int $attempt,
        array $result
    ): void {
        WhatsappMessageLog::query()->updateOrCreate(
            ['event_hash' => hash('sha256', sprintf('outbound:%d:%d', $message->id, $attempt))],
            [
                'message_id' => $message->id,
                'conversation_id' => $conversation->id,
                'wa_message_id' => $result['wa_message_id'] ?? null,
                'direction' => WhatsappMessageLog::DIRECTION_OUTBOUND,
                'signature_valid' => true,
                'http_status' => $result['http_status'],
                'processing_status' => $result['ok']
                    ? WhatsappMessageLog::PROCESS_PROCESSED
                    : WhatsappMessageLog::PROCESS_FAILED,
                'payload' => $result['raw'],
                'error_message' => $result['error_message'] ?? null,
                'processed_at' => now(),
            ]
        );
    }
}
