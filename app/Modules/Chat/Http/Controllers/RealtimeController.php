<?php

namespace App\Modules\Chat\Http\Controllers;

use App\Modules\Chat\ChatServiceProvider;
use App\Modules\Chat\Http\Controllers\Concerns\FormatsChatResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Broadcast;

class RealtimeController extends Controller
{
    use FormatsChatResponse;

    /**
     * Authenticates Sanctum-authenticated mobile clients for Reverb/Pusher private channels.
     */
    public function authenticate(Request $request): mixed
    {
        if (! Broadcast::getChannels()->has('conversation.{conversationId}')) {
            ChatServiceProvider::registerBroadcastChannels();
        }

        return Broadcast::auth($request);
    }

    /**
     * Returns public (non-secret) WebSocket connection parameters for mobile clients.
     */
    public function config(Request $request): JsonResponse
    {
        $driver = (string) config('broadcasting.default', 'reverb');
        $conn = config('broadcasting.connections.' . $driver, []);

        return $this->ok([
            'driver' => $driver,
            'key' => $conn['key'] ?? env('REVERB_APP_KEY') ?? env('PUSHER_APP_KEY'),
            'host' => $conn['options']['host'] ?? env('REVERB_HOST', 'ws.neonflux.my.id'),
            'port' => (int) ($conn['options']['port'] ?? env('REVERB_PORT', 443)),
            'scheme' => $conn['options']['scheme'] ?? env('REVERB_SCHEME', 'https'),
            'cluster' => $conn['options']['cluster'] ?? env('PUSHER_APP_CLUSTER', 'mt1'),
            'auth_endpoint' => '/api/v1/chat/broadcasting/auth',
        ], 'Konfigurasi realtime chat berhasil dimuat.');
    }
}
