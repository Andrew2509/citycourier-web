<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DanaConnection;
use App\Models\Wallet;
use App\Services\DanaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * DANA Controller — Widget Binding Flow.
 *
 * Official Flow:
 * 1. POST /connect → applyOTT → return redirect URL
 * 2. Flutter opens DANA App with redirect URL
 * 3. User authorizes in DANA
 * 4. DANA redirects to callback with authCode
 * 5. POST /callback → applyToken → connection established
 * 6. POST /disconnect → accountUnbinding → connection revoked
 *
 * PRD §36: API Backend endpoints for DANA.
 */
class DanaController extends Controller
{
    private DanaService $danaService;

    public function __construct(?DanaService $danaService = null)
    {
        $this->danaService = $danaService ?? new DanaService();
    }

    /**
     * GET /api/courier/dana/status
     * Get DANA connection status.
     */
    public function status(Request $request)
    {
        $courier = $request->user()->courier;
        if (!$courier) {
            return response()->json(['success' => false, 'message' => 'Profil kurir tidak ditemukan.'], 404);
        }

        $connection = DanaConnection::where('courier_id', $courier->id)->first();

        return response()->json([
            'success' => true,
            'data'    => [
                'status'       => $connection?->status ?? 'not_connected',
                'masked_phone' => $connection?->masked_phone,
                'connected_at' => $connection?->linked_at,
            ],
        ]);
    }

    /**
     * POST /api/courier/dana/connect
     * Step 1: Begin binding — call ApplyOTT, return redirect URL.
     * PRD §7: Hubungkan DANA
     */
    public function connect(Request $request)
    {
        $courier = $request->user()->courier;
        if (!$courier) {
            return response()->json(['success' => false, 'message' => 'Profil kurir tidak ditemukan.'], 404);
        }

        // Check if already connected
        $existing = DanaConnection::where('courier_id', $courier->id)
            ->where('status', 'connected')
            ->first();

        if ($existing) {
            return response()->json(['success' => false, 'message' => 'Akun DANA sudah terhubung.'], 400);
        }

        // Step 1: Apply OTT (One Time Token)
        $result = $this->danaService->beginBinding($courier->id);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'data'    => [
                    'session_id'     => $result['ott'],
                    'redirect_url'   => $result['redirect_url'],
                    'status'         => 'PENDING',
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['error'] ?? 'Gagal membuat sesi penghubungan.',
        ], 500);
    }

    /**
     * POST /api/courier/dana/callback
     * Step 4: Handle DANA callback — exchange authCode for accessToken.
     * PRD §10: DANA valid → CONNECTED
     */
    public function callback(Request $request)
    {
        $authCode = $request->input('authCode') ?? $request->input('authorization_code');
        $state    = $request->input('state');
        $status   = $request->input('status');

        if (!$authCode) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid callback: missing authCode.',
            ], 400);
        }

        // Find courier from state/OTT
        $courierId = null;
        if ($state) {
            $connection = DanaConnection::where('session_id', $state)->first();
            if ($connection) {
                $courierId = $connection->courier_id;
            }
        }

        if (!$courierId) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi tidak ditemukan.',
            ], 404);
        }

        // Step 4: Apply Token — exchange authCode for accessToken
        $result = $this->danaService->completeBinding($courierId, $authCode);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'DANA berhasil terhubung.',
                'data'    => [
                    'status'       => 'connected',
                    'masked_phone' => $result['masked_phone'],
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['error'] ?? 'Penghubungan DANA gagal.',
        ], 400);
    }

    /**
     * POST /api/courier/dana/mock-connect
     * Mock connection for development (PRD §73).
     */
    public function mockConnect(Request $request)
    {
        $courier = $request->user()->courier;
        if (!$courier) {
            return response()->json(['success' => false, 'message' => 'Profil kurir tidak ditemukan.'], 404);
        }

        // Simulate complete binding with mock data
        $result = $this->danaService->completeBinding($courier->id, 'MOCK-AUTH-CODE');

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'data'    => [
                    'status'       => 'connected',
                    'masked_account' => $result['masked_phone'],
                ],
                'message' => 'DANA berhasil terhubung (mode development).',
            ]);
        }

        // If completeBinding fails, create mock connection directly
        $maskedPhone = '081234******7890';

        $connection = DanaConnection::updateOrCreate(
            ['courier_id' => $courier->id],
            [
                'status'             => 'connected',
                'masked_phone'       => $maskedPhone,
                'provider_reference' => 'MOCK-AUTH-CODE',
                'access_token'       => 'MOCK-ACCESS-TOKEN',
                'linked_at'          => now(),
            ]
        );

        Wallet::updateOrCreate(
            ['courier_id' => $courier->id],
            ['status'     => 'active']
        );

        Log::info('[DanaController] Mock connection created', ['courier_id' => $courier->id]);

        return response()->json([
            'success' => true,
            'data'    => [
                'status'         => 'connected',
                'masked_account' => $maskedPhone,
            ],
            'message' => 'DANA berhasil terhubung (mode development).',
        ]);
    }

    /**
     * POST /api/courier/dana/disconnect
     * Revoke DANA connection.
     * PRD §34: Putuskan DANA
     */
    public function disconnect(Request $request)
    {
        $courier = $request->user()->courier;
        if (!$courier) {
            return response()->json(['success' => false, 'message' => 'Profil kurir tidak ditemukan.'], 404);
        }

        // Check for processing withdrawals
        $pendingWithdrawals = \App\Models\Withdrawal::where('courier_id', $courier->id)
            ->whereIn('status', ['reserved', 'processing'])
            ->count();

        if ($pendingWithdrawals > 0) {
            return response()->json([
                'success' => false,
                'message' => 'DANA tidak dapat diputuskan karena masih terdapat transaksi penarikan yang sedang diproses.',
            ], 400);
        }

        $result = $this->danaService->unbindAccount($courier->id);

        if ($result['success']) {
            return response()->json(['success' => true, 'message' => 'DANA berhasil diputuskan.']);
        }

        return response()->json([
            'success' => false,
            'message' => $result['error'] ?? 'Gagal memutuskan DANA.',
        ], 400);
    }

    /**
     * POST /api/courier/dana/reconnect
     * Reset connection and start new binding.
     * PRD §35: Ganti Akun DANA
     */
    public function reconnect(Request $request)
    {
        $courier = $request->user()->courier;
        if (!$courier) {
            return response()->json(['success' => false, 'message' => 'Profil kurir tidak ditemukan.'], 404);
        }

        // Reset existing connection
        $connection = DanaConnection::where('courier_id', $courier->id)->first();
        if ($connection) {
            $connection->update(['status' => 'not_connected', 'revoked_at' => now()]);
        }

        // Start new binding flow
        return $this->connect($request);
    }

    /**
     * POST /api/courier/dana/verify
     * Verify DANA account via phone number (Account Inquiry).
     * PRD §8-10: Alternative verification method.
     */
    public function verify(Request $request)
    {
        $request->validate(['phone_number' => 'required|string|min:10|max:15']);

        $courier = $request->user()->courier;
        if (!$courier) {
            return response()->json(['success' => false, 'message' => 'Profil kurir tidak ditemukan.'], 404);
        }

        $phoneNumber = preg_replace('/^(\+62|62)/', '0', $request->input('phone_number'));

        // For now, use mock verification
        // In production, this would call DANA Account Inquiry API
        $maskedPhone = substr($phoneNumber, 0, 6) . '******' . substr($phoneNumber, -4);

        $connection = DanaConnection::updateOrCreate(
            ['courier_id' => $courier->id],
            [
                'status'             => 'connected',
                'masked_phone'       => $maskedPhone,
                'provider_reference' => $phoneNumber,
                'linked_at'          => now(),
            ]
        );

        Wallet::updateOrCreate(
            ['courier_id' => $courier->id],
            ['status'     => 'active']
        );

        Log::info('[DanaController] DANA verified via phone', [
            'courier_id'   => $courier->id,
            'masked_phone' => $maskedPhone,
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'status'         => 'connected',
                'masked_account' => $maskedPhone,
            ],
            'message' => 'Akun DANA berhasil diverifikasi.',
        ]);
    }
}
