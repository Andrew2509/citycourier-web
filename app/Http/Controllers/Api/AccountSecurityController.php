<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AccountSecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AccountSecurityController extends Controller
{
    public function __construct(
        private readonly AccountSecurityService $security,
    ) {}

    /**
     * Ringkasan status keamanan akun, skor, preferensi, perangkat tepercaya, dan log aktivitas.
     * GET /api/security/overview
     */
    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'message' => 'Data keamanan akun berhasil dimuat.',
            'data' => $this->security->buildOverview($user, $request),
        ]);
    }

    /**
     * Buat atau ubah PIN transaksi 6 digit.
     * POST /api/security/pin
     */
    public function updatePin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'pin' => ['required', 'string', 'regex:/^\d{6}$/'],
            'pin_confirmation' => ['required', 'same:pin'],
            'current_pin' => ['nullable', 'string', 'regex:/^\d{6}$/'],
            'otp' => ['nullable', 'string', 'min:4', 'max:8'],
        ], [
            'pin.regex' => 'PIN transaksi harus terdiri dari 6 digit angka.',
            'pin_confirmation.same' => 'Konfirmasi PIN tidak cocok dengan PIN baru.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $result = $this->security->setOrChangePin(
            $user,
            (string) $request->input('pin'),
            $request->input('current_pin'),
            $request->input('otp'),
            $request
        );

        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'success' => false,
                'code' => $result['code'] ?? 'PIN_UPDATE_FAILED',
                'message' => $result['message'] ?? 'Gagal memperbarui PIN.',
                'attempts_left' => $result['attempts_left'] ?? null,
                'retry_after' => $result['retry_after'] ?? null,
            ], (int) ($result['status_code'] ?? 422));
        }

        $user->refresh();

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => $this->security->buildOverview($user, $request),
        ]);
    }

    /**
     * Kirim OTP 6 digit via WhatsApp untuk reset PIN yang terlupa.
     * POST /api/security/pin/request-otp
     */
    public function requestPinOtp(Request $request): JsonResponse
    {
        $result = $this->security->requestPinResetOtp($request->user(), $request);

        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'success' => false,
                'code' => $result['code'] ?? 'OTP_SEND_FAILED',
                'message' => $result['message'] ?? 'Gagal mengirim OTP reset PIN.',
                'retry_after' => $result['retry_after'] ?? null,
            ], (int) ($result['status_code'] ?? 422));
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'retry_after' => $result['retry_after'] ?? 60,
            'expires_in' => $result['expires_in'] ?? 300,
        ]);
    }

    /**
     * Verifikasi PIN transaksi 6 digit (untuk aksi sensitif & transaksi wallet).
     * POST /api/security/pin/verify
     */
    public function verifyPin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'pin' => ['required', 'string', 'regex:/^\d{6}$/'],
        ], [
            'pin.regex' => 'PIN transaksi harus terdiri dari 6 digit angka.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $result = $this->security->verifyPin(
            $request->user(),
            (string) $request->input('pin'),
            $request,
            silentSuccessLog: true
        );

        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'success' => false,
                'code' => $result['code'] ?? 'PIN_INVALID',
                'message' => $result['message'] ?? 'PIN transaksi salah.',
                'attempts_left' => $result['attempts_left'] ?? null,
                'retry_after' => $result['retry_after'] ?? null,
            ], (int) ($result['status_code'] ?? 422));
        }

        return response()->json([
            'success' => true,
            'message' => 'PIN transaksi valid.',
            'data' => ['valid' => true],
        ]);
    }

    /**
     * Perbarui preferensi biometrik & notifikasi keamanan.
     * PATCH /api/security/preferences
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'biometric_enabled' => ['sometimes', 'boolean'],
            'security_alerts_enabled' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $this->security->updatePreferences($user, $validator->validated(), $request);

        return response()->json([
            'success' => true,
            'message' => 'Preferensi keamanan berhasil diperbarui.',
            'data' => $this->security->buildOverview($user->fresh(), $request),
        ]);
    }

    /**
     * Daftar perangkat tepercaya & riwayat aktivitas keamanan.
     * GET /api/security/devices
     */
    public function devices(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentToken = $user->currentAccessToken();
        $this->security->attachTokenMetadata($currentToken, $request);

        return response()->json([
            'success' => true,
            'message' => 'Daftar perangkat tepercaya berhasil dimuat.',
            'data' => [
                'devices' => $this->security->listDevices($user, $currentToken),
                'activity_logs' => $this->security->recentLogs($user, 25),
            ],
        ]);
    }

    /**
     * Sinkronisasi metadata perangkat ke sesi token aktif.
     * POST /api/security/devices/register
     */
    public function registerDevice(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentToken = $user->currentAccessToken();
        $this->security->attachTokenMetadata($currentToken, $request);

        return response()->json([
            'success' => true,
            'message' => 'Perangkat tepercaya berhasil disinkronkan.',
            'data' => [
                'devices' => $this->security->listDevices($user, $currentToken),
            ],
        ]);
    }

    /**
     * Hapus perangkat tepercaya tertentu (cabut Sanctum token terkait).
     * DELETE /api/security/devices/{tokenId}
     */
    public function revokeDevice(Request $request, int $tokenId): JsonResponse
    {
        $user = $request->user();
        $result = $this->security->revokeDevice($user, $tokenId, $user->currentAccessToken(), $request);

        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'success' => false,
                'code' => $result['code'] ?? 'REVOKE_FAILED',
                'message' => $result['message'] ?? 'Gagal menghapus perangkat.',
            ], (int) ($result['status_code'] ?? 422));
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => $this->security->buildOverview($user->fresh(), $request),
        ]);
    }

    /**
     * Keluar dari semua perangkat lain kecuali sesi saat ini.
     * POST /api/security/devices/revoke-others
     */
    public function revokeOtherDevices(Request $request): JsonResponse
    {
        $user = $request->user();
        $result = $this->security->revokeOtherDevices($user, $user->currentAccessToken(), $request);

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'revoked_count' => $result['revoked_count'] ?? 0,
            'data' => $this->security->buildOverview($user->fresh(), $request),
        ]);
    }

    /**
     * Riwayat aktivitas keamanan akun.
     * GET /api/security/logs
     */
    public function logs(Request $request): JsonResponse
    {
        $limit = (int) $request->integer('limit', 25);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat aktivitas keamanan berhasil dimuat.',
            'data' => [
                'logs' => $this->security->recentLogs($request->user(), $limit),
            ],
        ]);
    }
}
