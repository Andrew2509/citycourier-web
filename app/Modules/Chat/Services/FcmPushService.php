<?php

namespace App\Modules\Chat\Services;

use App\Modules\Chat\Models\CallSession;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\Message;
use App\Modules\Chat\Models\UserDeviceToken;
use App\Modules\Chat\Support\AvatarUrlResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FcmPushService
{
    public function isConfigured(): bool
    {
        return (bool) config('fcm.enabled', false)
            && ! empty(config('fcm.project_id'))
            && (! empty(config('fcm.static_access_token')) || is_file((string) config('fcm.credentials_path', '')));
    }

    public function notifyNewMessage(Conversation $conversation, Message $message): int
    {
        if (! $this->isConfigured() || $message->sender_role === Message::ROLE_SYSTEM) {
            return 0;
        }

        $recipientUserId = $message->sender_role === Message::ROLE_CUSTOMER
            ? $conversation->courier_user_id
            : $conversation->customer_user_id;

        if ($recipientUserId === null) {
            return 0;
        }

        $message->loadMissing('sender');
        $senderName = $message->sender?->name
            ?? ($message->sender_role === Message::ROLE_COURIER ? 'Kurir City Courier' : 'Customer');
        $orderRef = $conversation->order_number ?: ('#' . $conversation->order_id);
        $channelId = (string) config('fcm.android_channel_id', 'city_courier_chat');

        return $this->sendToUserTokens($recipientUserId, [
            'notification' => [
                'title' => sprintf('%s • %s', $senderName, $orderRef),
                'body' => $message->previewText(140),
            ],
            'data' => [
                'type' => 'chat_message',
                'conversation_id' => (string) $conversation->id,
                'order_id' => (string) $conversation->order_id,
                'order_number' => (string) $orderRef,
                'message_id' => (string) $message->id,
                'client_message_id' => (string) ($message->client_message_id ?? ''),
                'sender_role' => (string) $message->sender_role,
            ],
            'android' => [
                'priority' => 'HIGH',
                'collapse_key' => 'conv_' . $conversation->id,
                'notification' => [
                    'channel_id' => $channelId,
                ],
            ],
        ]);
    }

    /**
     * Sends high-priority data-only FCM push so Android background/killed isolate handles full-screen ringing.
     */
    public function notifyIncomingCall(CallSession $callSession): int
    {
        if (! $this->isConfigured()) {
            return 0;
        }

        $callSession->loadMissing(['conversation', 'caller.courier']);
        $conv = $callSession->conversation;
        $callerIsCourier = $conv !== null && $conv->courier_user_id !== null
            && (int) $conv->courier_user_id === (int) $callSession->caller_id;
        $callerName = $callSession->caller?->name
            ?? ($callerIsCourier ? 'Kurir City Courier' : 'Customer');
        $callerAvatar = AvatarUrlResolver::resolveForUser($callSession->caller, $callerIsCourier) ?? '';
        $orderRef = $conv?->order_number ?: ($callSession->order_id !== null ? ('#' . $callSession->order_id) : '');
        $ttl = max(15, (int) config('voip.ring_timeout_seconds', 45)) . 's';

        return $this->sendToUserTokens((int) $callSession->receiver_id, [
            'data' => [
                'type' => 'incoming_call',
                'call_id' => (string) $callSession->id,
                'call_uuid' => (string) $callSession->call_uuid,
                'conversation_id' => (string) $callSession->conversation_id,
                'order_id' => (string) ($callSession->order_id ?? ''),
                'order_number' => (string) $orderRef,
                'caller_id' => (string) $callSession->caller_id,
                'caller_name' => (string) $callerName,
                'caller_role' => $callerIsCourier ? Message::ROLE_COURIER : Message::ROLE_CUSTOMER,
                'caller_avatar' => (string) $callerAvatar,
                'expires_at' => (string) ($callSession->expires_at?->toIso8601String() ?? ''),
            ],
            'android' => [
                'priority' => 'HIGH',
                'ttl' => $ttl,
                'collapse_key' => 'call_' . $callSession->id,
            ],
        ]);
    }

    public function notifyCallCancelled(CallSession $callSession): int
    {
        if (! $this->isConfigured()) {
            return 0;
        }

        return $this->sendToUserTokens((int) $callSession->receiver_id, [
            'data' => [
                'type' => 'call_cancelled',
                'call_id' => (string) $callSession->id,
                'call_uuid' => (string) $callSession->call_uuid,
                'status' => (string) $callSession->status,
            ],
            'android' => [
                'priority' => 'HIGH',
                'ttl' => '30s',
                'collapse_key' => 'call_' . $callSession->id,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $messageBody
     */
    private function sendToUserTokens(int $recipientUserId, array $messageBody): int
    {
        $tokens = UserDeviceToken::query()
            ->where('user_id', $recipientUserId)
            ->where('is_active', true)
            ->pluck('fcm_token')
            ->all();

        if ($tokens === []) {
            return 0;
        }

        $accessToken = $this->resolveAccessToken();
        if ($accessToken === null) {
            return 0;
        }

        $endpoint = sprintf(
            'https://fcm.googleapis.com/v1/projects/%s/messages:send',
            (string) config('fcm.project_id')
        );

        $sentCount = 0;
        foreach ($tokens as $deviceToken) {
            $payload = [
                'message' => array_merge($messageBody, [
                    'token' => $deviceToken,
                ]),
            ];

            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout((int) config('fcm.timeout_seconds', 10))
                ->post($endpoint, $payload);

            if ($response->successful()) {
                $sentCount++;
                continue;
            }

            $bodyStr = (string) $response->body();
            if (
                in_array($response->status(), [404, 410], true)
                || str_contains($bodyStr, 'UNREGISTERED')
            ) {
                UserDeviceToken::query()
                    ->where('fcm_token', $deviceToken)
                    ->update(['is_active' => false]);
            }
        }

        return $sentCount;
    }

    private function resolveAccessToken(): ?string
    {
        $static = config('fcm.static_access_token');
        if (is_string($static) && $static !== '') {
            return $static;
        }

        $credentialsPath = (string) config('fcm.credentials_path', '');
        if ($credentialsPath === '' || ! is_file($credentialsPath)) {
            return null;
        }

        $cacheKey = 'citycourier:fcm:oauth:' . md5($credentialsPath);
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $raw = file_get_contents($credentialsPath);
        $creds = is_string($raw) ? json_decode($raw, true) : null;
        if (! is_array($creds) || empty($creds['client_email']) || empty($creds['private_key'])) {
            return null;
        }

        $now = time();
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claim = $this->base64UrlEncode(json_encode([
            'iss' => $creds['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));

        $signingInput = $header . '.' . $claim;
        $signature = '';
        if (! openssl_sign($signingInput, $signature, (string) $creds['private_key'], OPENSSL_ALGO_SHA256)) {
            return null;
        }

        $jwt = $signingInput . '.' . $this->base64UrlEncode($signature);
        $tokenRes = Http::asForm()
            ->timeout((int) config('fcm.timeout_seconds', 10))
            ->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

        if (! $tokenRes->successful()) {
            return null;
        }

        $accessToken = $tokenRes->json('access_token');
        if (! is_string($accessToken) || $accessToken === '') {
            return null;
        }

        Cache::put($cacheKey, $accessToken, now()->addMinutes(50));

        return $accessToken;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
