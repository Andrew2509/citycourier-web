<?php

namespace App\Modules\Chat\Http\Controllers;

use App\Modules\Chat\Http\Controllers\Concerns\FormatsChatResponse;
use App\Modules\Chat\Http\Requests\RegisterDeviceTokenRequest;
use App\Modules\Chat\Models\UserDeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DeviceTokenController extends Controller
{
    use FormatsChatResponse;

    public function store(RegisterDeviceTokenRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $userId = (int) $request->user()->getAuthIdentifier();

        $token = UserDeviceToken::query()->updateOrCreate(
            ['fcm_token' => $validated['fcm_token']],
            [
                'user_id' => $userId,
                'device_id' => $validated['device_id'] ?? null,
                'platform' => $validated['platform'] ?? 'android',
                'is_active' => true,
                'last_seen_at' => now(),
            ]
        );

        return $this->ok([
            'id' => (int) $token->id,
            'platform' => $token->platform,
            'is_active' => (bool) $token->is_active,
        ], 'Token notifikasi perangkat tersimpan.');
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => ['required', 'string', 'max:512'],
        ]);

        UserDeviceToken::query()
            ->where('user_id', (int) $request->user()->getAuthIdentifier())
            ->where('fcm_token', $validated['fcm_token'])
            ->update(['is_active' => false]);

        return $this->ok(null, 'Token notifikasi perangkat dinonaktifkan.');
    }
}
