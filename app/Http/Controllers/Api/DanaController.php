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

        // Step 1: Apply OTT / Deeplink Binding (One Time Token)
        $phone = $courier->phone ?? $request->input('phone_number');
        $result = $this->danaService->beginBinding($courier->id, $phone);

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
     * GET/POST /api/courier/dana/callback
     * Step 4: Handle DANA callback — exchange authCode for accessToken.
     * DANA me-redirect browser user ke endpoint ini (publik, tanpa bearer token).
     * PRD §10: DANA valid → CONNECTED
     */
    public function callback(Request $request)
    {
        // DANA Deeplink Binding redirect: ?auth_code=xxx&state=yyy
        $authCode = $request->query('auth_code')
            ?? $request->input('auth_code')
            ?? $request->input('authCode')
            ?? $request->input('authorization_code');
        $state    = $request->query('state') ?? $request->input('state');

        if (!$authCode) {
            return $this->renderCallbackResult(false, 'Kode otorisasi DANA tidak ditemukan.');
        }

        // Cari kurir dari state (disimpan sebagai session_id saat beginBinding)
        $courierId = null;
        if ($state) {
            $connection = DanaConnection::where('session_id', $state)->first();
            if ($connection) {
                $courierId = $connection->courier_id;
            }
        }

        if (!$courierId) {
            Log::warning('[DanaController] callback session tidak ditemukan', ['state' => $state]);
            return $this->renderCallbackResult(false, 'Sesi penghubungan tidak ditemukan atau kedaluwarsa.');
        }

        // Step 4: Apply Token — exchange authCode for accessToken
        $result = $this->danaService->completeBinding($courierId, $authCode);

        if ($result['success']) {
            Log::info('[DanaController] callback success', [
                'courier_id'   => $courierId,
                'masked_phone' => $result['masked_phone'],
            ]);
            return $this->renderCallbackResult(true, 'DANA berhasil terhubung.', $result['masked_phone']);
        }

        return $this->renderCallbackResult(false, $result['error'] ?? 'Penghubungan DANA gagal.');
    }

    /**
     * Render simple HTML result page untuk browser user setelah callback DANA.
     */
    private function renderCallbackResult(bool $success, string $message, ?string $maskedPhone = null)
    {
        $appUrl = config('app.url', url('/'));
        $html = '<!DOCTYPE html><html lang="id"><head><meta name="viewport" content="width=device-width, initial-scale=1"><meta charset="utf-8"><title>City Courier - DANA</title></head>'
            . '<body style="font-family:sans-serif;background:#f5f5f5;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:16px;">'
            . '<div style="background:#fff;border-radius:16px;padding:32px;max-width:400px;width:100%;text-align:center;box-shadow:0 4px 24px rgba(0,0,0,.08);">'
            . '<div style="font-size:48px;margin-bottom:12px;">' . ($success ? '&#9989;' : '&#10060;') . '</div>'
            . '<h2 style="margin:0 0 8px;color:' . ($success ? '#22c55e' : '#ef4444') . ';">' . ($success ? 'DANA Terhubung' : 'Gagal') . '</h2>'
            . '<p style="color:#555;margin:0 0 8px;word-break:break-word;">' . e($message) . '</p>'
            . ($maskedPhone ? '<p style="color:#888;font-size:14px;margin:0 0 16px;">Akun: ' . e($maskedPhone) . '</p>' : '')
            . '<p style="color:#999;font-size:13px;margin:16px 0 0;">Silakan kembali ke aplikasi City Courier.</p>'
            . '</div></body></html>';

        return response($html, $success ? 200 : 400)->header('Content-Type', 'text/html');
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
