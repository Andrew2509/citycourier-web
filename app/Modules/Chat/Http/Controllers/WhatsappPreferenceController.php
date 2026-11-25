<?php

namespace App\Modules\Chat\Http\Controllers;

use App\Modules\Chat\Http\Controllers\Concerns\FormatsChatResponse;
use App\Modules\Chat\Http\Requests\WhatsappOptInRequest;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\WhatsappContact;
use App\Modules\Chat\Support\PhoneNumberNormalizer;
use App\Modules\Payment\Services\GatewayCredentialsResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WhatsappPreferenceController extends Controller
{
    use FormatsChatResponse;

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = (int) $user->getAuthIdentifier();
        $contact = WhatsappContact::query()->where('user_id', $userId)->first();
        $cloudCreds = app(GatewayCredentialsResolver::class)->whatsappCloud();

        return $this->ok([
            'whatsapp_cloud_enabled' => (bool) ($cloudCreds['enabled'] ?? false),
            'phone_e164' => $contact?->wa_phone_e164 ?? PhoneNumberNormalizer::toE164($user->phone ?? null),
            'phone_verified' => $this->isUserPhoneVerified($user),
            'opt_in_status' => $contact?->opt_in_status ?? WhatsappContact::OPT_PENDING,
            'window_open' => $contact?->isWindowOpen() ?? false,
            'window_expires_at' => $contact?->window_expires_at?->toIso8601String(),
        ], 'Status preferensi WhatsApp berhasil dimuat.');
    }

    public function updateOptIn(WhatsappOptInRequest $request): JsonResponse
    {
        $user = $request->user();
        $userId = (int) $user->getAuthIdentifier();
        $phoneE164 = PhoneNumberNormalizer::toE164($user->phone ?? null);

        if ($phoneE164 === null) {
            return $this->fail(
                'Nomor telepon akun belum valid. Lengkapi dan verifikasi nomor terlebih dahulu.',
                'PHONE_INVALID',
                422
            );
        }

        if (! $this->isUserPhoneVerified($user)) {
            return $this->fail(
                'Nomor telepon harus terverifikasi OTP sebelum mengaktifkan jembatan WhatsApp.',
                'PHONE_NOT_VERIFIED',
                422
            );
        }

        $validated = $request->validated();
        $optIn = (bool) $validated['opt_in'];
        $source = $validated['source'] ?? 'app_toggle';

        if (! empty($validated['conversation_id'])) {
            $conversation = Conversation::query()->find((int) $validated['conversation_id']);
            if ($conversation === null || ! $conversation->isParticipant($userId)) {
                return $this->fail('Percakapan tidak ditemukan untuk pengguna ini.', 'FORBIDDEN', 403);
            }
        }

        $contact = WhatsappContact::query()->firstOrNew(['user_id' => $userId]);
        $contact->wa_phone_e164 = $phoneE164;

        if (! empty($validated['conversation_id'])) {
            $contact->active_conversation_id = (int) $validated['conversation_id'];
        }

        if ($optIn) {
            $contact->markOptedIn($source);
        } else {
            $contact->markOptedOut($source);
        }
        $contact->save();

        $cloudCreds = app(GatewayCredentialsResolver::class)->whatsappCloud();

        return $this->ok([
            'whatsapp_cloud_enabled' => (bool) ($cloudCreds['enabled'] ?? false),
            'phone_e164' => $contact->wa_phone_e164,
            'phone_verified' => true,
            'opt_in_status' => $contact->opt_in_status,
            'window_open' => $contact->isWindowOpen(),
            'window_expires_at' => $contact->window_expires_at?->toIso8601String(),
        ], $optIn ? 'Jembatan notifikasi WhatsApp diaktifkan.' : 'Jembatan notifikasi WhatsApp dinonaktifkan.');
    }

    private function isUserPhoneVerified(mixed $user): bool
    {
        if (isset($user->phone_verified_at) && $user->phone_verified_at !== null) {
            return true;
        }

        if (isset($user->phone_verified)) {
            return (bool) $user->phone_verified;
        }

        return false;
    }
}
