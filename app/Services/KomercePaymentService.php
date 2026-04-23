<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KomercePaymentService
{
    protected string $apiKey;
    protected string $baseUrl;
    protected string $callbackKey;

    public function __construct()
    {
        // Prioritas: Database (admin panel) → config/.env
        $apiKey      = Setting::get('komerce_payment_api_key') ?: config('services.komerce_payment.api_key', '');
        $env         = Setting::get('komerce_payment_env')     ?: config('services.komerce_payment.env', 'sandbox');
        $callbackKey = Setting::get('komerce_payment_callback_key') ?: config('services.komerce_payment.callback_key', '');

        $this->apiKey      = $apiKey;
        $this->callbackKey = $callbackKey;

        // Tentukan base URL berdasarkan environment
        $this->baseUrl = $env === 'production'
            ? 'https://api.komerce.id'
            : 'https://api-sandbox.komerce.id';
    }

    /**
     * HTTP headers yang dibutuhkan API.
     */
    private function headers(): array
    {
        return [
            'x-api-key'    => $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];
    }

    /**
     * 1. Ambil daftar metode pembayaran (VA & QRIS).
     * GET /api/v1/user/methods
     */
    public function getPaymentMethods(): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->get("{$this->baseUrl}/api/v1/user/methods");

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Komerce Payment: getPaymentMethods failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return ['meta' => ['code' => $response->status(), 'status' => 'error'], 'data' => []];
        } catch (\Exception $e) {
            Log::error('Komerce Payment: getPaymentMethods exception', ['error' => $e->getMessage()]);
            return ['meta' => ['code' => 500, 'status' => 'error', 'message' => $e->getMessage()], 'data' => []];
        }
    }

    /**
     * 2. Buat transaksi pembayaran (VA atau QRIS).
     * POST /api/v1/user/payment/create
     *
     * @param array $data {
     *   order_id: string,
     *   payment_type: 'bank_transfer'|'qris',
     *   channel_code: string (e.g. BCA, BNI — tidak perlu untuk QRIS),
     *   amount: int (min 10000),
     *   customer: {name, email, phone},
     *   items: [{name, quantity, price}],
     *   expiry_duration?: int (detik, min 3600),
     *   callback_url?: string,
     * }
     */
    public function createPayment(array $data): array
    {
        try {
            // Tambahkan callback_api_key jika callback_url diisi
            if (!empty($data['callback_url']) && empty($data['callback_api_key'])) {
                $data['callback_api_key'] = $this->callbackKey;
            }

            $response = Http::withHeaders($this->headers())
                ->post("{$this->baseUrl}/api/v1/user/payment/create", $data);

            Log::info('Komerce Payment: createPayment', [
                'request' => $data,
                'status'  => $response->status(),
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Komerce Payment: createPayment failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            $errorBody = $response->json();
            return [
                'meta' => [
                    'code'    => $response->status(),
                    'status'  => 'error',
                    'message' => $errorBody['meta']['message'] ?? 'Payment creation failed',
                ],
                'data' => null,
            ];
        } catch (\Exception $e) {
            Log::error('Komerce Payment: createPayment exception', ['error' => $e->getMessage()]);
            return [
                'meta' => ['code' => 500, 'status' => 'error', 'message' => $e->getMessage()],
                'data' => null,
            ];
        }
    }

    /**
     * 3. Cek status pembayaran.
     * GET /api/v1/user/payment/status/{payment_id}
     */
    public function getPaymentStatus(string $paymentId): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->get("{$this->baseUrl}/api/v1/user/payment/status/{$paymentId}");

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Komerce Payment: getPaymentStatus failed', [
                'payment_id' => $paymentId,
                'status'     => $response->status(),
            ]);

            return ['meta' => ['code' => $response->status(), 'status' => 'error'], 'data' => null];
        } catch (\Exception $e) {
            Log::error('Komerce Payment: getPaymentStatus exception', ['error' => $e->getMessage()]);
            return ['meta' => ['code' => 500, 'status' => 'error', 'message' => $e->getMessage()], 'data' => null];
        }
    }

    /**
     * 4. Batalkan pembayaran.
     * POST /api/v1/user/payment/cancel
     */
    public function cancelPayment(string $paymentId, string $reason = 'Canceled by user'): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->post("{$this->baseUrl}/api/v1/user/payment/cancel", [
                    'payment_id' => $paymentId,
                    'reason'     => $reason,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Komerce Payment: cancelPayment failed', [
                'payment_id' => $paymentId,
                'status'     => $response->status(),
                'body'       => $response->body(),
            ]);

            $errorBody = $response->json();
            return [
                'meta' => [
                    'code'    => $response->status(),
                    'status'  => 'error',
                    'message' => $errorBody['meta']['message'] ?? 'Cancel payment failed',
                ],
                'data' => null,
            ];
        } catch (\Exception $e) {
            Log::error('Komerce Payment: cancelPayment exception', ['error' => $e->getMessage()]);
            return ['meta' => ['code' => 500, 'status' => 'error', 'message' => $e->getMessage()], 'data' => null];
        }
    }
}
