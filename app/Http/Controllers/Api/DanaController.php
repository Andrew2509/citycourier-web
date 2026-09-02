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
     * Get DANA connection status (PRD §34).
     * Otomatis menandai EXPIRED bila token kedaluwarsa (PRD §33).
     */
    public function status(Request $request)
    {
        $courier = $request->user()->courier;
        if (!$courier) {
            return response()->json([
                'success'    => false,
                'message'    => 'Profil kurir tidak ditemukan.',
                'error_code' => 'SESSION_EXPIRED',
            ], 404);
        }

        $connection = $this->danaService->freshStatus($courier->id);

        $status = $connection?->status ?? 'not_connected';
        $label  = $connection?->status_label ?? 'NOT_CONNECTED';

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data'    => [
                'status'         => $label, // PRD §14: NOT_CONNECTED / PENDING / CONNECTED / ...
                'status_label'   => $label,
                'provider'       => 'DANA',
                'connected'      => $status === 'connected',
                'masked_account' => $connection?->masked_phone,
                'masked_phone'   => $connection?->masked_phone,
                'bound_at'       => $connection?->bound_at?->toIso8601String(),
                'connected_at'   => $connection?->linked_at,
                'external_id'    => $connection?->external_id,
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
            return response()->json([
                'success'    => false,
                'message'    => 'Profil kurir tidak ditemukan.',
                'error_code' => 'SESSION_EXPIRED',
            ], 404);
        }

        // Check if already connected
        $existing = DanaConnection::where('courier_id', $courier->id)
            ->where('status', 'connected')
            ->first();

        if ($existing) {
            return response()->json([
                'success'    => false,
                'message'    => 'Akun DANA sudah terhubung.',
                'error_code' => 'DANA_ALREADY_CONNECTED',
            ], 400);
        }

        // Step 1: Apply OTT / Deeplink Binding (One Time Token)
        $phone = $courier->phone ?? $request->input('phone_number');
        $result = $this->danaService->beginBinding($courier->id, $phone);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'DANA binding URL generated',
                'data'    => [
                    'session_id'     => $result['ott'],
                    'redirect_url'   => $result['redirect_url'],
                    'binding_url'    => $result['redirect_url'],
                    'status'         => 'PENDING',
                ],
            ]);
        }

        return response()->json([
            'success'    => false,
            'message'    => $result['error'] ?? 'Gagal membuat sesi penghubungan.',
            'error_code' => 'DANA_BINDING_FAILED',
        ], 500);
    }

    /**
     * POST /api/courier/dana/binding
     * Generate binding URL (PRD §34). Alias resmi dari connect().
     */
    public function binding(Request $request)
    {
        $courier = $request->user()->courier;
        if (!$courier) {
            return response()->json([
                'success'    => false,
                'message'    => 'Profil kurir tidak ditemukan.',
                'error_code' => 'SESSION_EXPIRED',
            ], 404);
        }

        $existing = DanaConnection::where('courier_id', $courier->id)
            ->where('status', 'connected')
            ->first();

        if ($existing) {
            return response()->json([
                'success'    => false,
                'message'    => 'Akun DANA sudah terhubung.',
                'error_code' => 'DANA_ALREADY_CONNECTED',
            ], 400);
        }

        $phone = $courier->phone ?? $request->input('phone_number');
        $result = $this->danaService->beginBinding($courier->id, $phone);

        if ($result['success']) {
            $connection = DanaConnection::where('courier_id', $courier->id)->first();

            return response()->json([
                'success'    => true,
                'message'    => 'DANA binding URL generated',
                'data'       => [
                    'binding_url'  => $result['redirect_url'],
                    'redirect_url' => $result['redirect_url'],
                    'session_id'   => $result['ott'],
                    'expires_at'   => $connection?->session_expires_at?->toIso8601String(),
                ],
                // Backward-compat legacy fields (konsumen lama connect()) tetap jalan.
                'bindingUrl' => $result['redirect_url'],
                'sessionId'  => $result['ott'],
            ]);
        }

        return response()->json([
            'success'    => false,
            'message'    => $result['error'] ?? 'Gagal membuat sesi penghubungan.',
            'error_code' => 'DANA_BINDING_FAILED',
        ], 500);
    }

    /**
     * POST /api/dana/bind/init
     * Alias panduan integrasi: inisialisasi binding → return URL + state.
     */
    public function initBinding(Request $request)
    {
        $courier = $request->user()->courier;
        if (!$courier) {
            return response()->json(['error' => 'Profil kurir tidak ditemukan.'], 404);
        }

        $existing = DanaConnection::where('courier_id', $courier->id)
            ->where('status', 'connected')
            ->first();

        if ($existing) {
            return response()->json(['error' => 'Akun DANA sudah terhubung.', 'state' => null], 400);
        }

        $phone = $courier->phone ?? $request->input('phone_number');
        $result = $this->danaService->beginBinding($courier->id, $phone);

        if ($result['success']) {
            return response()->json([
                'binding_url' => $result['redirect_url'],
                'state'       => $result['ott'],
                'redirect_url'=> $result['redirect_url'],
            ]);
        }

        return response()->json([
            'error'  => $result['error'] ?? 'Gagal membuat sesi penghubungan.',
        ], 500);
    }

    /**
     * POST /api/dana/bind/status
     * Alias panduan integrasi: cek status binding → JSON.
     */
    public function checkBindingStatus(Request $request)
    {
        $courier = $request->user()->courier;
        if (!$courier) {
            return response()->json(['error' => 'Profil kurir tidak ditemukan.'], 404);
        }

        $connection = DanaConnection::where('courier_id', $courier->id)->first();

        return response()->json([
            'status'       => $connection?->status ?? 'not_connected',
            'masked_phone' => $connection?->masked_phone,
            'connected_at' => $connection?->linked_at,
        ]);
    }

    /**
     * POST /api/dana/webhook
     * Alias panduan integrasi: callback webhook DANA → exchange authCode, return JSON.
     */
    public function webhookCallback(Request $request)
    {
        $authCode = $request->input('auth_code')
            ?? $request->input('authCode')
            ?? $request->input('authorization_code');
        $state    = $request->input('state');

        if (!$authCode) {
            return response()->json(['status' => 'failed', 'error' => 'Kode otorisasi tidak ditemukan.'], 400);
        }

        $courierId = null;
        if ($state) {
            $resolved = $this->danaService->resolveSession($state);
            if ($resolved['valid']) {
                $courierId = $resolved['connection']->courier_id;
            } else {
                Log::warning('[DanaController] webhook session tidak valid', ['state' => $state, 'error' => $resolved['error']]);
                return response()->json(['status' => 'failed', 'error' => $resolved['error'], 'error_code' => $resolved['error_code'] ?? 'INVALID_STATE'], 400);
            }
        }

        if (!$courierId) {
            Log::warning('[DanaController] webhook session tidak ditemukan', ['state' => $state]);
            return response()->json(['status' => 'failed', 'error' => 'Sesi penghubungan tidak ditemukan atau kedaluwarsa.', 'error_code' => 'INVALID_STATE'], 400);
        }

        $result = $this->danaService->completeBinding($courierId, $authCode);

        if ($result['success']) {
            Log::info('[DanaController] webhook success', [
                'courier_id'   => $courierId,
                'masked_phone' => $result['masked_phone'],
            ]);
            return response()->json([
                'status'       => 'success',
                'masked_phone' => $result['masked_phone'],
            ]);
        }

        return response()->json([
            'status'     => 'failed',
            'error'      => $result['error'] ?? 'Penghubungan DANA gagal.',
            'error_code' => $result['error_code'] ?? 'DANA_BINDING_FAILED',
        ], 400);
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
        // (doc update memakai authCode; beberapa versi OAuth memakai "code")
        $authCode = $request->query('auth_code')
            ?? $request->input('auth_code')
            ?? $request->input('authCode')
            ?? $request->input('code')
            ?? $request->input('authorization_code');
        $state    = $request->query('state') ?? $request->input('state');

        if (!$authCode) {
            // Diagnostik: catat nama parameter yang datang dari DANA (tanpa nilai,
            // agar kode/state tidak bocor ke log).
            Log::warning('[DanaController] callback tanpa auth_code', [
                'query_keys'  => array_keys($request->query()),
                'input_keys'  => array_keys($request->input()),
            ]);

            $errorMsg = $request->input('error')
                ?? $request->input('responseMessage')
                ?? $request->input('message')
                ?? 'Kode otorisasi DANA tidak ditemukan.';

            return $this->renderCallbackResult(false, $errorMsg);
        }

        // Cari kurir dari state (disimpan sebagai state_hash saat beginBinding)
        $courierId = null;
        if ($state) {
            $resolved = $this->danaService->resolveSession($state);
            if ($resolved['valid']) {
                $courierId = $resolved['connection']->courier_id;
            } else {
                Log::warning('[DanaController] callback session tidak valid', ['state' => $state, 'error' => $resolved['error']]);
                return $this->renderCallbackResult(false, $resolved['error'] ?? 'Sesi penghubungan tidak ditemukan atau kedaluwarsa.');
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
     * Render simple HTML result page untuk browser user setelah callback DANA,
     * lalu auto-redirect kembali ke aplikasi Flutter via deep link (PRD §12).
     */
    private function renderCallbackResult(bool $success, string $message, ?string $maskedPhone = null)
    {
        $appUrl = config('app.url', url('/'));
        $deepLink = $success
            ? 'citycourier://dana/binding/success'
            : 'citycourier://dana/binding/failed';
        $html = '<!DOCTYPE html><html lang="id"><head><meta name="viewport" content="width=device-width, initial-scale=1"><meta charset="utf-8">'
            . '<meta http-equiv="refresh" content="1;url=' . e($deepLink) . '">'
            . '<title>City Courier - DANA</title></head>'
            . '<body style="font-family:sans-serif;background:#f5f5f5;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:16px;">'
            . '<div style="background:#fff;border-radius:16px;padding:32px;max-width:400px;width:100%;text-align:center;box-shadow:0 4px 24px rgba(0,0,0,.08);">'
            . '<div style="font-size:48px;margin-bottom:12px;">' . ($success ? '&#9989;' : '&#10060;') . '</div>'
            . '<h2 style="margin:0 0 8px;color:' . ($success ? '#22c55e' : '#ef4444') . ';">' . ($success ? 'DANA Terhubung' : 'Gagal') . '</h2>'
            . '<p style="color:#555;margin:0 0 8px;word-break:break-word;">' . e($message) . '</p>'
            . ($maskedPhone ? '<p style="color:#888;font-size:14px;margin:0 0 16px;">Akun: ' . e($maskedPhone) . '</p>' : '')
            . '<p style="color:#999;font-size:13px;margin:16px 0 0;">Mengembalikan ke aplikasi CityCourier...</p>'
            . '</div></body></html>';

        return response($html, $success ? 200 : 400)->header('Content-Type', 'text/html');
    }

    /**
     * POST /api/courier/dana/mock-connect
     * Mock connection for development (PRD §73).
     * Mendaftarkan koneksi DANA simulasi langsung ke database, tanpa
     * memanggil API DANA. Perilaku selanjutnya sama seperti akun asli
     * (status, saldo, tarik dana) di-backend.
     */
    public function mockConnect(Request $request)
    {
        $courier = $request->user()->courier;
        if (!$courier) {
            return response()->json(['success' => false, 'message' => 'Profil kurir tidak ditemukan.'], 404);
        }

        // Reset sesi binding apapun agar status konsisten → connected.
        DanaConnection::where('courier_id', $courier->id)
            ->update(['status' => 'not_connected']);

        // Simulasi akun DANA test (nomor demo).
        $maskedPhone = '08******1234';

        $connection = DanaConnection::updateOrCreate(
            ['courier_id' => $courier->id],
            [
                'status'             => 'connected',
                'masked_phone'       => $maskedPhone,
                'provider_reference' => 'DEMO-' . strtoupper(Str::random(8)),
                'external_id'        => 'DEMO-' . Str::random(16),
                'access_token'       => 'DEMO-ACCESS-TOKEN',
                'linked_at'          => now(),
                'bound_at'           => now(),
            ]
        );

        // Aktifkan wallet kurir.
        Wallet::updateOrCreate(
            ['courier_id' => $courier->id],
            ['status' => 'active']
        );

        Log::info('[DanaController] Mock connection (demo) created', ['courier_id' => $courier->id]);

        return response()->json([
            'success' => true,
            'data'    => [
                'status'         => 'connected',
                'masked_account' => $maskedPhone,
            ],
            'message' => 'DANA berhasil terhubung (mode development/demo).',
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
            return response()->json([
                'success'    => false,
                'message'    => 'Profil kurir tidak ditemukan.',
                'error_code' => 'SESSION_EXPIRED',
            ], 404);
        }

        // Check for processing withdrawals
        $pendingWithdrawals = \App\Models\Withdrawal::where('courier_id', $courier->id)
            ->whereIn('status', ['reserved', 'processing'])
            ->count();

        if ($pendingWithdrawals > 0) {
            return response()->json([
                'success'    => false,
                'message'    => 'DANA tidak dapat diputuskan karena masih terdapat transaksi penarikan yang sedang diproses.',
                'error_code' => 'DANA_NOT_CONNECTED',
            ], 400);
        }

        $result = $this->danaService->unbindAccount($courier->id);

        if ($result['success']) {
            return response()->json(['success' => true, 'message' => 'DANA berhasil diputuskan.']);
        }

        if (($result['error_code'] ?? '') === 'DANA_NOT_CONNECTED') {
            return response()->json([
                'success'    => false,
                'message'    => $result['error'] ?? 'Akun DANA belum terhubung.',
                'error_code' => 'DANA_NOT_CONNECTED',
            ], 400);
        }

        return response()->json([
            'success'    => false,
            'message'    => $result['error'] ?? 'Gagal memutuskan DANA.',
            'error_code' => 'DANA_BINDING_FAILED',
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
