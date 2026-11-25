<?php

namespace App\Modules\WhatsApp\Services;

use App\Models\User;
use App\Modules\Chat\Models\WhatsappContact;
use App\Modules\Chat\Models\WhatsappMessageLog;
use App\Modules\Chat\Support\PhoneNumberNormalizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Processes verified Meta WhatsApp Business Cloud API webhook events
 * (delivery status callbacks and contact opt-in/opt-out window updates)
 * for automated order notifications without internal chat dependencies.
 */
class WhatsAppWebhookProcessor
{
    private const STATUS_RANK = [
        'accepted' => 0,
        'failed' => 1,
        'sent' => 2,
        'delivered' => 3,
        'read' => 4,
    ];

    public function processLog(WhatsappMessageLog $log): void
    {
        $payload = $log->payload ?? [];
        $entries = is_array($payload['entry'] ?? null) ? $payload['entry'] : [];

        $linkedWaMessageId = $log->wa_message_id;
        $processedAny = false;

        foreach ($entries as $entry) {
            $changes = is_array($entry['changes'] ?? null) ? $entry['changes'] : [];
            foreach ($changes as $change) {
                $value = is_array($change['value'] ?? null) ? $change['value'] : [];

                foreach (($value['statuses'] ?? []) as $statusPayload) {
                    if (is_array($statusPayload)) {
                        $statusWaId = $this->handleStatusUpdate($statusPayload);
                        if ($statusWaId !== null) {
                            $linkedWaMessageId ??= $statusWaId;
                            $processedAny = true;
                        }
                    }
                }

                foreach (($value['messages'] ?? []) as $incomingPayload) {
                    if (is_array($incomingPayload)) {
                        $inboundWaId = $this->handleIncomingMessage($incomingPayload);
                        if ($inboundWaId !== null) {
                            $linkedWaMessageId = $inboundWaId;
                            $processedAny = true;
                        }
                    }
                }
            }
        }

        $log->forceFill([
            'wa_message_id' => $linkedWaMessageId,
            'processing_status' => $processedAny || $linkedWaMessageId !== null
                ? WhatsappMessageLog::PROCESS_PROCESSED
                : WhatsappMessageLog::PROCESS_IGNORED,
            'processed_at' => now(),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $statusPayload
     */
    private function handleStatusUpdate(array $statusPayload): ?string
    {
        $waMessageId = (string) ($statusPayload['id'] ?? '');
        $rawStatus = strtolower((string) ($statusPayload['status'] ?? ''));
        if ($waMessageId === '' || $rawStatus === '') {
            return null;
        }

        $outboundLog = WhatsappMessageLog::query()
            ->where('direction', WhatsappMessageLog::DIRECTION_OUTBOUND)
            ->where('wa_message_id', $waMessageId)
            ->latest('id')
            ->first();

        if ($outboundLog === null) {
            return $waMessageId;
        }

        $payload = is_array($outboundLog->payload) ? $outboundLog->payload : [];
        $currentStatus = strtolower((string) ($payload['wa_status'] ?? 'accepted'));
        $currentRank = self::STATUS_RANK[$currentStatus] ?? 0;
        $nextRank = self::STATUS_RANK[$rawStatus] ?? -1;

        if ($nextRank > $currentRank) {
            $eventTime = isset($statusPayload['timestamp']) && is_numeric($statusPayload['timestamp'])
                ? Carbon::createFromTimestamp((int) $statusPayload['timestamp'])
                : now();

            $payload['wa_status'] = $rawStatus;
            $payload['wa_status_updated_at'] = $eventTime->toIso8601String();
            if ($rawStatus === 'failed') {
                $firstError = is_array($statusPayload['errors'][0] ?? null) ? $statusPayload['errors'][0] : [];
                $payload['wa_error_code'] = isset($firstError['code']) ? (string) $firstError['code'] : null;
                $payload['wa_error_message'] = isset($firstError['title']) ? (string) $firstError['title'] : null;
                $outboundLog->error_message = $payload['wa_error_message'];
            }

            $outboundLog->payload = $payload;
            $outboundLog->save();
        }

        return $waMessageId;
    }

    /**
     * @param  array<string, mixed>  $waMsg
     */
    private function handleIncomingMessage(array $waMsg): ?string
    {
        $waMessageId = (string) ($waMsg['id'] ?? '');
        $fromPhone = PhoneNumberNormalizer::toE164((string) ($waMsg['from'] ?? ''));

        if ($waMessageId === '' || $fromPhone === null) {
            return null;
        }

        $inboundAt = isset($waMsg['timestamp']) && is_numeric($waMsg['timestamp'])
            ? Carbon::createFromTimestamp((int) $waMsg['timestamp'])
            : now();

        $contact = $this->resolveOrSyncContact($fromPhone);
        if ($contact === null) {
            return $waMessageId;
        }

        $contact->openWindow($inboundAt);

        $rawType = strtolower((string) ($waMsg['type'] ?? 'text'));
        if ($rawType === 'text') {
            $normalizedKeyword = strtoupper(trim((string) ($waMsg['text']['body'] ?? '')));
            $optOutKeywords = array_map('strtoupper', config('whatsapp.opt_out_keywords', ['STOP', 'UNSUBSCRIBE', 'BERHENTI']));
            if (in_array($normalizedKeyword, $optOutKeywords, true)) {
                $contact->markOptedOut('stop_keyword');
                $contact->save();

                return $waMessageId;
            }
        }

        if ($contact->opt_in_status === WhatsappContact::OPT_PENDING) {
            $contact->markOptedIn('inbound_wa');
        }
        $contact->save();

        return $waMessageId;
    }

    private function resolveOrSyncContact(string $phoneE164): ?WhatsappContact
    {
        $contact = WhatsappContact::query()->where('wa_phone_e164', $phoneE164)->first();
        if ($contact !== null) {
            return $contact;
        }

        $instance = new User();
        if (! Schema::hasTable($instance->getTable())) {
            return null;
        }

        $candidateLocal = str_starts_with($phoneE164, '62') ? '0' . substr($phoneE164, 2) : null;
        $candidates = array_values(array_filter([
            $phoneE164,
            '+' . $phoneE164,
            $candidateLocal,
        ]));

        $user = User::query()->whereIn('phone', $candidates)->first();
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
}
