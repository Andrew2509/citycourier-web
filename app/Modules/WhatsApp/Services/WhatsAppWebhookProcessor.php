<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\Chat\Events\InternalChatMessagePersisted;
use App\Modules\Chat\Events\MessageStatusUpdatedBroadcast;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\Message;
use App\Modules\Chat\Models\WhatsappContact;
use App\Modules\Chat\Models\WhatsappMessageLog;
use App\Modules\Chat\Support\PhoneNumberNormalizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WhatsAppWebhookProcessor
{
    private const STATUS_RANK = [
        Message::STATUS_SENDING => 0,
        Message::STATUS_FAILED => 1,
        Message::STATUS_SENT => 2,
        Message::STATUS_DELIVERED => 3,
        Message::STATUS_READ => 4,
    ];

    public function processLog(WhatsappMessageLog $log): void
    {
        $payload = $log->payload ?? [];
        $entries = is_array($payload['entry'] ?? null) ? $payload['entry'] : [];

        $linkedConversationId = $log->conversation_id;
        $linkedMessageId = $log->message_id;
        $linkedWaMessageId = $log->wa_message_id;

        foreach ($entries as $entry) {
            $changes = is_array($entry['changes'] ?? null) ? $entry['changes'] : [];
            foreach ($changes as $change) {
                $value = is_array($change['value'] ?? null) ? $change['value'] : [];

                foreach (($value['statuses'] ?? []) as $statusPayload) {
                    if (is_array($statusPayload)) {
                        $msg = $this->handleStatusUpdate($statusPayload);
                        if ($msg !== null) {
                            $linkedMessageId ??= $msg->id;
                            $linkedConversationId ??= $msg->conversation_id;
                            $linkedWaMessageId ??= $msg->wa_message_id;
                        }
                    }
                }

                foreach (($value['messages'] ?? []) as $incomingPayload) {
                    if (is_array($incomingPayload)) {
                        $msg = $this->handleIncomingMessage($incomingPayload);
                        if ($msg !== null) {
                            $linkedMessageId = $msg->id;
                            $linkedConversationId = $msg->conversation_id;
                            $linkedWaMessageId = $msg->wa_message_id;
                        }
                    }
                }
            }
        }

        $log->forceFill([
            'conversation_id' => $linkedConversationId,
            'message_id' => $linkedMessageId,
            'wa_message_id' => $linkedWaMessageId,
            'processing_status' => $linkedMessageId !== null || $linkedWaMessageId !== null
                ? WhatsappMessageLog::PROCESS_PROCESSED
                : WhatsappMessageLog::PROCESS_IGNORED,
            'processed_at' => now(),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $statusPayload
     */
    private function handleStatusUpdate(array $statusPayload): ?Message
    {
        $waMessageId = (string) ($statusPayload['id'] ?? '');
        $rawStatus = strtolower((string) ($statusPayload['status'] ?? ''));
        if ($waMessageId === '' || $rawStatus === '') {
            return null;
        }

        $message = Message::query()->where('wa_message_id', $waMessageId)->first();
        if ($message === null) {
            return null;
        }

        $mappedDeliveryStatus = match ($rawStatus) {
            'sent' => Message::STATUS_SENT,
            'delivered' => Message::STATUS_DELIVERED,
            'read' => Message::STATUS_READ,
            'failed' => Message::STATUS_FAILED,
            default => null,
        };

        $eventTime = isset($statusPayload['timestamp']) && is_numeric($statusPayload['timestamp'])
            ? Carbon::createFromTimestamp((int) $statusPayload['timestamp'])
            : now();

        $updates = ['wa_status' => $rawStatus];
        $currentRank = self::STATUS_RANK[$message->delivery_status] ?? 0;
        $nextRank = $mappedDeliveryStatus !== null ? (self::STATUS_RANK[$mappedDeliveryStatus] ?? 0) : -1;

        $statusProgressed = false;
        if ($mappedDeliveryStatus !== null && $nextRank > $currentRank) {
            $updates['delivery_status'] = $mappedDeliveryStatus;
            $statusProgressed = true;

            if ($mappedDeliveryStatus === Message::STATUS_DELIVERED) {
                $updates['delivered_at'] = $message->delivered_at ?? $eventTime;
            } elseif ($mappedDeliveryStatus === Message::STATUS_READ) {
                $updates['delivered_at'] = $message->delivered_at ?? $eventTime;
                $updates['read_at'] = $message->read_at ?? $eventTime;
            } elseif ($mappedDeliveryStatus === Message::STATUS_FAILED) {
                $firstError = is_array($statusPayload['errors'][0] ?? null) ? $statusPayload['errors'][0] : [];
                $updates['failed_at'] = $message->failed_at ?? $eventTime;
                $updates['wa_error_code'] = isset($firstError['code']) ? (string) $firstError['code'] : null;
                $updates['wa_error_message'] = isset($firstError['title']) ? (string) $firstError['title'] : null;
            }
        }

        $message->forceFill($updates)->save();

        if ($statusProgressed && $message->conversation !== null && $mappedDeliveryStatus !== null) {
            try {
                MessageStatusUpdatedBroadcast::dispatch(
                    $message->conversation,
                    [(int) $message->id],
                    $mappedDeliveryStatus,
                    $eventTime->toIso8601String()
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $message;
    }

    /**
     * @param  array<string, mixed>  $waMsg
     */
    private function handleIncomingMessage(array $waMsg): ?Message
    {
        $waMessageId = (string) ($waMsg['id'] ?? '');
        $fromPhone = PhoneNumberNormalizer::toE164((string) ($waMsg['from'] ?? ''));

        if ($waMessageId === '' || $fromPhone === null) {
            return null;
        }

        // Idempotent guard per wamid
        $existing = Message::query()->where('wa_message_id', $waMessageId)->first();
        if ($existing !== null) {
            return $existing;
        }

        $inboundAt = isset($waMsg['timestamp']) && is_numeric($waMsg['timestamp'])
            ? Carbon::createFromTimestamp((int) $waMsg['timestamp'])
            : now();

        $contact = $this->resolveOrSyncContact($fromPhone);
        if ($contact === null) {
            return null;
        }

        $contact->openWindow($inboundAt);

        [$messageType, $body, $metadata] = $this->extractContent($waMsg);
        $normalizedKeyword = strtoupper(trim((string) ($body ?? '')));
        $optOutKeywords = array_map('strtoupper', config('whatsapp.opt_out_keywords', ['STOP']));

        if ($messageType === Message::TYPE_TEXT && in_array($normalizedKeyword, $optOutKeywords, true)) {
            $contact->markOptedOut('stop_keyword');
            $contact->save();

            return null;
        }

        // Sending an inbound reply to our official WABA counts as active engagement if previously pending
        if ($contact->opt_in_status === WhatsappContact::OPT_PENDING) {
            $contact->markOptedIn('inbound_wa');
        }
        $contact->save();

        $conversation = $this->matchConversation($contact, $waMsg);
        if ($conversation === null) {
            return null;
        }

        $persisted = DB::transaction(function () use (
            $conversation,
            $contact,
            $waMessageId,
            $messageType,
            $body,
            $metadata,
            $inboundAt
        ): Message {
            /** @var Conversation $lockedConversation */
            $lockedConversation = Conversation::query()
                ->whereKey($conversation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $duplicate = Message::query()->where('wa_message_id', $waMessageId)->first();
            if ($duplicate !== null) {
                return $duplicate;
            }

            $senderRole = $lockedConversation->roleForUser($contact->user_id) ?? Message::ROLE_CUSTOMER;

            $message = Message::create([
                'conversation_id' => $lockedConversation->id,
                'sender_user_id' => $contact->user_id,
                'sender_role' => $senderRole,
                'client_message_id' => 'wa-' . $waMessageId,
                'channel' => Message::CHANNEL_WHATSAPP,
                'message_type' => $messageType,
                'body' => $body,
                'delivery_status' => Message::STATUS_SENT,
                'wa_message_id' => $waMessageId,
                'wa_status' => 'received',
                'metadata' => $metadata,
                'sent_at' => $inboundAt,
            ]);

            $recipientUnreadCol = $senderRole === Message::ROLE_CUSTOMER
                ? 'courier_unread_count'
                : 'customer_unread_count';

            $lockedConversation->forceFill([
                'last_message_id' => $message->id,
                'last_message_preview' => $message->previewText(),
                'last_message_at' => $inboundAt,
                $recipientUnreadCol => (int) $lockedConversation->{$recipientUnreadCol} + 1,
            ])->save();

            return $message->load('sender');
        });

        $contact->forceFill(['active_conversation_id' => $conversation->id])->save();
        InternalChatMessagePersisted::dispatch($conversation->fresh(), $persisted);

        return $persisted;
    }

    private function resolveOrSyncContact(string $phoneE164): ?WhatsappContact
    {
        $contact = WhatsappContact::query()->where('wa_phone_e164', $phoneE164)->first();
        if ($contact !== null) {
            return $contact;
        }

        $userModelClass = config('chat.host_models.user');
        if (! $userModelClass || ! class_exists($userModelClass)) {
            return null;
        }

        $instance = new $userModelClass();
        if (! Schema::hasTable($instance->getTable())) {
            return null;
        }

        $candidateLocal = str_starts_with($phoneE164, '62') ? '0' . substr($phoneE164, 2) : null;
        $candidates = array_values(array_filter([
            $phoneE164,
            '+' . $phoneE164,
            $candidateLocal,
        ]));

        $user = $userModelClass::query()->whereIn('phone', $candidates)->first();
        if ($user === null) {
            return null;
        }

        return WhatsappContact::create([
            'user_id' => (int) $user->getKey(),
            'wa_phone_e164' => $phoneE164,
            'opt_in_status' => WhatsappContact::OPT_IN,
            'opt_in_source' => 'inbound_wa',
            'opted_in_at' => now(),
        ]);
    }

    /**
     * Resolves which active conversation an incoming WhatsApp message belongs to:
     * 1) Explicit quoted reply context.id -> original conversation
     * 2) Contact's active_conversation_id if still active & participant
     * 3) Most recently updated active conversation for the user.
     *
     * @param  array<string, mixed>  $waMsg
     */
    private function matchConversation(WhatsappContact $contact, array $waMsg): ?Conversation
    {
        $contextWaId = $waMsg['context']['id'] ?? null;
        if (is_string($contextWaId) && $contextWaId !== '') {
            $repliedMessage = Message::query()->where('wa_message_id', $contextWaId)->first();
            $repliedConversation = $repliedMessage?->conversation;
            if (
                $repliedConversation !== null
                && $repliedConversation->status === Conversation::STATUS_ACTIVE
                && $repliedConversation->isParticipant($contact->user_id)
            ) {
                return $repliedConversation;
            }
        }

        if ($contact->active_conversation_id !== null) {
            $activeConv = Conversation::query()->find($contact->active_conversation_id);
            if (
                $activeConv !== null
                && $activeConv->status === Conversation::STATUS_ACTIVE
                && $activeConv->isParticipant($contact->user_id)
            ) {
                return $activeConv;
            }
        }

        return Conversation::query()
            ->forParticipant($contact->user_id)
            ->where('status', Conversation::STATUS_ACTIVE)
            ->orderByRaw('COALESCE(last_message_at, created_at) DESC')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $waMsg
     * @return array{0: string, 1: ?string, 2: array<string, mixed>}
     */
    private function extractContent(array $waMsg): array
    {
        $rawType = strtolower((string) ($waMsg['type'] ?? 'text'));

        if ($rawType === 'text') {
            return [
                Message::TYPE_TEXT,
                (string) ($waMsg['text']['body'] ?? ''),
                ['wa_type' => 'text'],
            ];
        }

        if ($rawType === 'image') {
            $caption = trim((string) ($waMsg['image']['caption'] ?? ''));

            return [
                Message::TYPE_IMAGE,
                $caption !== '' ? $caption : '[Gambar dari WhatsApp]',
                [
                    'wa_type' => 'image',
                    'wa_media_id' => $waMsg['image']['id'] ?? null,
                    'wa_mime_type' => $waMsg['image']['mime_type'] ?? null,
                ],
            ];
        }

        if ($rawType === 'document') {
            $filename = (string) ($waMsg['document']['filename'] ?? 'Dokumen');

            return [
                Message::TYPE_DOCUMENT,
                '[Dokumen WhatsApp: ' . $filename . ']',
                [
                    'wa_type' => 'document',
                    'wa_media_id' => $waMsg['document']['id'] ?? null,
                    'filename' => $filename,
                ],
            ];
        }

        return [
            Message::TYPE_TEXT,
            sprintf('[Pesan WhatsApp tipe %s]', $rawType),
            ['wa_type' => $rawType],
        ];
    }
}
