<?php

namespace App\Modules\Chat\Services;

class IceServerConfigProvider
{
    /**
     * Returns ICE server configurations exclusively for authenticated participants of an active call.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forParticipant(int $userId, bool $includeTurn = true): array
    {
        $servers = [];

        $stunUrls = (array) config('voip.stun_urls', []);
        if ($stunUrls !== []) {
            $servers[] = [
                'urls' => count($stunUrls) === 1 ? $stunUrls[0] : array_values($stunUrls),
            ];
        }

        if (! $includeTurn) {
            return $servers;
        }

        $turnUrls = (array) config('voip.turn_urls', []);
        if ($turnUrls === []) {
            return $servers;
        }

        $sharedSecret = trim((string) config('voip.turn_shared_secret', ''));
        if ($sharedSecret !== '') {
            $ttl = max(60, (int) config('voip.turn_ttl_seconds', 3600));
            $username = (now()->timestamp + $ttl) . ':' . $userId;
            $credential = base64_encode(hash_hmac('sha1', $username, $sharedSecret, true));

            $servers[] = [
                'urls' => count($turnUrls) === 1 ? $turnUrls[0] : array_values($turnUrls),
                'username' => $username,
                'credential' => $credential,
            ];

            return $servers;
        }

        $turnUsername = trim((string) config('voip.turn_username', ''));
        $turnCredential = trim((string) config('voip.turn_credential', ''));
        if ($turnUsername !== '' && $turnCredential !== '') {
            $servers[] = [
                'urls' => count($turnUrls) === 1 ? $turnUrls[0] : array_values($turnUrls),
                'username' => $turnUsername,
                'credential' => $turnCredential,
            ];
        }

        return $servers;
    }
}
