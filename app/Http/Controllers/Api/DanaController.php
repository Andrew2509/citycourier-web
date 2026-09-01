<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DanaConnection;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DanaController extends Controller
{
    /**
     * Get DANA connection status
     * GET /api/courier/dana/status
     */
    public function status(Request $request)
    {
        $courier = $request->user()->courier;

        if (!$courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        $connection = DanaConnection::where('courier_id', $courier->id)->first();

        if (!$connection) {
            return response()->json([
                'success' => true,
                'data' => [
                    'status' => 'not_connected',
                    'masked_phone' => null,
                    'connected_at' => null,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'status' => $connection->status,
                'masked_phone' => $connection->masked_phone,
                'connected_at' => $connection->linked_at,
            ],
        ]);
    }

    /**
     * Start DANA linking - creates authorization session
     * POST /api/courier/dana/connect
     */
    public function connect(Request $request)
    {
        $courier = $request->user()->courier;

        if (!$courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        // Check if already connected
        $existing = DanaConnection::where('courier_id', $courier->id)
            ->where('status', 'connected')
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Akun DANA sudah terhubung.',
            ], 400);
        }

        // Create or update connection record
        $sessionId = 'DANA-LINK-' . date('Ymd') . '-' . strtoupper(Str::random(8));
        
        $connection = DanaConnection::updateOrCreate(
            ['courier_id' => $courier->id],
            [
                'status' => 'pending',
                'session_id' => $sessionId,
                'session_expires_at' => now()->addMinutes(10),
            ]
        );

        // TODO: In production, call DANA API to get authorization URL
        // For now, return mock authorization URL
        $authorizationUrl = config('services.dana.authorization_url', 'https://sandbox.dana.id/authorization');
        $authorizationUrl .= '?' . http_build_query([
            'client_id' => config('services.dana.client_id', 'mock-client-id'),
            'redirect_uri' => config('services.dana.callback_url', url('/api/courier/dana/callback')),
            'state' => $sessionId,
            'scope' => 'payment',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'session_id' => $sessionId,
                'status' => 'PENDING',
                'authorization_url' => $authorizationUrl,
                'expires_at' => $connection->session_expires_at,
            ],
        ]);
    }

    /**
     * Handle DANA callback after authorization
     * POST /api/courier/dana/callback
     */
    public function callback(Request $request)
    {
        $state = $request->input('state');
        $authorizationCode = $request->input('authorization_code');
        $status = $request->input('status');

        if (!$state) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid callback: missing state.',
            ], 400);
        }

        // Find session
        $connection = DanaConnection::where('session_id', $state)->first();

        if (!$connection) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi tidak ditemukan.',
            ], 404);
        }

        // Check session expiry
        if ($connection->session_expires_at && now()->isAfter($connection->session_expires_at)) {
            $connection->update(['status' => 'expired']);
            return response()->json([
                'success' => false,
                'message' => 'Sesi penghubungan telah kedaluwarsa.',
            ], 400);
        }

        // Check if authorization was approved
        if ($status === 'approved' && $authorizationCode) {
            // Resolve courier from the connection record
            $courier = $connection->courier;

            // TODO: Exchange authorization code with DANA for access token
            // Store token securely in backend (not in Flutter)
            
            // For now, mock the masked phone
            $maskedPhone = '08******' . substr($courier->phone ?? '1234', -4);
            
            $connection->update([
                'status' => 'connected',
                'masked_phone' => $maskedPhone,
                'provider_reference' => $authorizationCode,
                'linked_at' => now(),
            ]);

            // Activate wallet
            Wallet::updateOrCreate(
                ['courier_id' => $connection->courier_id],
                ['status' => 'active']
            );

            return response()->json([
                'success' => true,
                'message' => 'DANA berhasil terhubung.',
                'data' => [
                    'status' => 'connected',
                    'masked_phone' => $maskedPhone,
                ],
            ]);
        } else {
            $connection->update(['status' => 'failed']);
            return response()->json([
                'success' => false,
                'message' => 'Penghubungan DANA gagal.',
            ], 400);
        }
    }

    /**
     * Simulate a successful DANA linking for development/mock mode.
     * POST /api/courier/dana/mock-connect
     *
     * Menandai koneksi sebagai connected langsung di database sehingga
     * status bertahan saat aplikasi dibuka ulang (tanpa DANA API asli).
     */
    public function mockConnect(Request $request)
    {
        $courier = $request->user()->courier;

        if (!$courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        $maskedPhone = '08******' . substr($courier->phone ?? '1234', -4);

        DanaConnection::updateOrCreate(
            ['courier_id' => $courier->id],
            [
                'status' => 'connected',
                'masked_phone' => $maskedPhone,
                'provider_reference' => 'MOCK-CONNECT',
                'linked_at' => now(),
                'revoked_at' => null,
            ]
        );

        Wallet::updateOrCreate(
            ['courier_id' => $courier->id],
            ['status' => 'active']
        );

        return response()->json([
            'success' => true,
            'message' => 'DANA berhasil terhubung (mode pengembangan).',
            'data' => [
                'status' => 'connected',
                'masked_phone' => $maskedPhone,
            ],
        ]);
    }

    /**
     * Disconnect DANA account
     * POST /api/courier/dana/disconnect
     */
    public function disconnect(Request $request)
    {
        $courier = $request->user()->courier;

        if (!$courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        $connection = DanaConnection::where('courier_id', $courier->id)
            ->where('status', 'connected')
            ->first();

        if (!$connection) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada koneksi DANA yang aktif.',
            ], 400);
        }

        // Check if there are pending withdrawals
        $pendingWithdrawals = \App\Models\Withdrawal::where('courier_id', $courier->id)
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        if ($pendingWithdrawals > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat memutuskan DANA karena ada penarikan yang sedang diproses.',
            ], 400);
        }

        $connection->update([
            'status' => 'revoked',
            'revoked_at' => now(),
        ]);

        // Deactivate wallet
        $wallet = Wallet::where('courier_id', $courier->id)->first();
        if ($wallet) {
            $wallet->update(['status' => 'not_active']);
        }

        return response()->json([
            'success' => true,
            'message' => 'DANA berhasil diputuskan.',
        ]);
    }
}
