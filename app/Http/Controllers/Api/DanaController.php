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
 * DANA Controller.
 * Handles DANA connection lifecycle: connect, verify, reconnect, disconnect, mock-connect.
 *
 * PRD §36: API Backend endpoints for DANA
 * PRD §73: MockDanaProvider used when credentials unavailable
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
     * Get DANA connection status for the authenticated courier.
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
                'data'    => [
                    'status'       => 'not_connected',
                    'masked_phone' => null,
                    'connected_at' => null,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'status'       => $connection->status,
                'masked_phone' => $connection->masked_phone,
                'connected_at' => $connection->linked_at,
            ],
        ]);
    }

    /**
     * POST /api/courier/dana/connect
     * Start DANA linking - creates authorization session.
     * PRD §7: Hubungkan DANA
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

        // Create linking session
        $sessionId = 'DANA-LINK-' . date('Ymd') . '-' . strtoupper(Str::random(8));

        $connection = DanaConnection::updateOrCreate(
            ['courier_id' => $courier->id],
            [
                'status'               => 'pending',
                'session_id'           => $sessionId,
                'session_expires_at'   => now()->addMinutes(10),
            ]
        );

        // Build authorization URL for DANA web/app flow
        $authorizationUrl = config('services.dana.authorization_url', 'https://sandbox.dana.id/authorization');
        $authorizationUrl .= '?' . http_build_query([
            'client_id'   => config('services.dana.client_id', 'mock-client-id'),
            'redirect_uri' => config('services.dana.callback_url', url('/api/courier/dana/callback')),
            'state'       => $sessionId,
            'scope'       => 'payment',
        ]);

        Log::info('[DanaController] Linking session created', [
            'courier_id' => $courier->id,
            'session_id' => $sessionId,
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'session_id'        => $sessionId,
                'status'            => 'PENDING',
                'authorization_url' => $authorizationUrl,
                'expires_at'        => $connection->session_expires_at,
            ],
        ]);
    }

    /**
     * POST /api/courier/dana/verify
     * Verify DANA account via Account Inquiry (phone number input).
     * PRD §8-10: Input Akun DANA → Account Inquiry → Valid/Invalid
     */
    public function verify(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|string|min:10|max:15',
        ]);

        $courier = $request->user()->courier;

        if (!$courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        $phoneNumber = $request->input('phone_number');

        // Normalize phone number (ensure starts with 08)
        $phoneNumber = preg_replace('/^(\+62|62)/', '0', $phoneNumber);

        // PRD §9: Account Inquiry
        $result = $this->danaService->accountInquiry($phoneNumber);

        if ($result['success']) {
            // Find or create connection
            $connection = DanaConnection::where('courier_id', $courier->id)->first();

            if (!$connection) {
                $connection = DanaConnection::create([
                    'courier_id'  => $courier->id,
                    'status'      => 'pending',
                    'session_id'  => 'DANA-VERIFY-' . date('Ymd') . '-' . strtoupper(Str::random(8)),
                ]);
            }

            // PRD §10: Complete connection
            $connection = $this->danaService->completeConnection($connection, $result['account_info']);

            Log::info('[DanaController] DANA verified and connected', [
                'courier_id'   => $courier->id,
                'masked_phone' => $result['masked_account'],
            ]);

            return response()->json([
                'success' => true,
                'data'    => [
                    'status'        => 'connected',
                    'masked_account' => $result['masked_account'],
                    'account_info'  => [
                        'masked_account' => $result['masked_account'],
                        'status'         => $result['account_info']['account_status'] ?? 'active',
                    ],
                ],
                'message' => 'Akun DANA berhasil diverifikasi dan terhubung.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['error'] ?? 'Akun DANA tidak ditemukan.',
        ], 400);
    }

    /**
     * POST /api/courier/dana/mock-connect
     * Simulate successful DANA linking for development.
     * Used by Flutter frontend in dev mode.
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

        // Use mock account info
        $mockAccountInfo = [
            'account_identifier' => '081234567890',
            'masked_account'     => '081234******7890',
            'account_status'     => 'active',
        ];

        $connection = DanaConnection::where('courier_id', $courier->id)->first();

        if (!$connection) {
            $connection = DanaConnection::create([
                'courier_id' => $courier->id,
                'status'     => 'pending',
            ]);
        }

        $connection = $this->danaService->completeConnection($connection, $mockAccountInfo);

        Log::info('[DanaController] Mock DANA connection created', ['courier_id' => $courier->id]);

        return response()->json([
            'success' => true,
            'data'    => [
                'status'        => 'connected',
                'masked_account' => $connection->masked_phone,
                'connected_at'  => $connection->linked_at,
            ],
            'message' => 'DANA berhasil terhubung (mode development).',
        ]);
    }

    /**
     * POST /api/courier/dana/callback
     * Handle DANA callback after authorization flow.
     */
    public function callback(Request $request)
    {
        $state            = $request->input('state');
        $authorizationCode = $request->input('authorization_code');
        $status           = $request->input('status');

        if (!$state) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid callback: missing state.',
            ], 400);
        }

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

        if ($status === 'approved' && $authorizationCode) {
            // TODO: Exchange authorization code with DANA for access token (production)
            $courier = $connection->courier;
            $maskedPhone = '08******' . substr($courier->phone ?? '1234', -4);

            $connection->update([
                'status'             => 'connected',
                'masked_phone'       => $maskedPhone,
                'provider_reference' => $authorizationCode,
                'linked_at'          => now(),
            ]);

            Wallet::updateOrCreate(
                ['courier_id' => $connection->courier_id],
                ['status'     => 'active']
            );

            return response()->json([
                'success' => true,
                'message' => 'DANA berhasil terhubung.',
                'data'    => [
                    'status'       => 'connected',
                    'masked_phone' => $maskedPhone,
                ],
            ]);
        }

        $connection->update(['status' => 'failed']);
        return response()->json([
            'success' => false,
            'message' => 'Penghubungan DANA gagal.',
        ], 400);
    }

    /**
     * POST /api/courier/dana/disconnect
     * Disconnect DANA account.
     * PRD §34: Putuskan DANA
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

        // PRD §34: Check for processing withdrawals
        $pendingWithdrawals = \App\Models\Withdrawal::where('courier_id', $courier->id)
            ->whereIn('status', ['reserved', 'processing'])
            ->count();

        if ($pendingWithdrawals > 0) {
            return response()->json([
                'success' => false,
                'message' => 'DANA tidak dapat diputuskan karena masih terdapat transaksi penarikan yang sedang diproses.',
            ], 400);
        }

        $connection->update([
            'status'     => 'revoked',
            'revoked_at' => now(),
        ]);

        // Deactivate wallet
        $wallet = Wallet::where('courier_id', $courier->id)->first();
        if ($wallet) {
            $wallet->update(['status' => 'not_active']);
        }

        Log::info('[DanaController] DANA disconnected', ['courier_id' => $courier->id]);

        return response()->json([
            'success' => true,
            'message' => 'DANA berhasil diputuskan.',
        ]);
    }

    /**
     * POST /api/courier/dana/reconnect
     * Reconnect/replace DANA account.
     * PRD §35: Ganti Akun DANA
     */
    public function reconnect(Request $request)
    {
        $courier = $request->user()->courier;

        if (!$courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        // Reset existing connection to allow new linking
        $connection = DanaConnection::where('courier_id', $courier->id)->first();

        if ($connection) {
            $connection->update([
                'status'     => 'not_connected',
                'revoked_at' => now(),
            ]);
        }

        // Create new session (same as connect flow)
        return $this->connect($request);
    }
}
