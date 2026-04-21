<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected $apiKey;
    protected $baseUrl;
    protected $deviceId;

    public function __construct()
    {
        $this->apiKey = \App\Models\Setting::get('orbitwa_api_key', config('services.orbitwa.api_key') ?? env('ORBITWA_API_KEY'));
        $this->baseUrl = \App\Models\Setting::get('orbitwa_base_url', config('services.orbitwa.base_url') ?? env('ORBITWA_BASE_URL', 'https://orbitwaapi.site/api/v1'));
        $this->deviceId = (int) \App\Models\Setting::get('orbitwa_device_id', env('ORBITWA_DEVICE_ID', 0));
    }

    /**
     * Send a WhatsApp message.
     *
     * @param string $to Recipient phone number (format: 628123456789)
     * @param string $message The message content
     * @return array
     */
    /**
     * Format phone number to international format (62...)
     */
    protected function formatPhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters (including +)
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        // Convert leading 0 to 62
        if (str_starts_with($cleaned, '0')) {
            $cleaned = '62' . substr($cleaned, 1);
        }

        // If it doesn't start with 62, prepend it (handling cases where user types 812...)
        if (!str_starts_with($cleaned, '62')) {
            $cleaned = '62' . $cleaned;
        }

        return $cleaned;
    }

    public function sendMessage(string $to, string $message)
    {
        $to = $this->formatPhoneNumber($to);

        if (!$this->apiKey) {
            Log::error('OrbitWA API Key is not set.');
            return [
                'success' => false,
                'message' => 'WhatsApp API Key is not configured.'
            ];
        }

        $payload = [
            'device_id' => $this->deviceId,
            'to' => $to,
            'message' => $message,
        ];

        try {
            $response = Http::withToken($this->apiKey)
                ->post($this->baseUrl . '/messages/send', $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            Log::error('OrbitWA API Error: ' . $response->body(), [
                'payload' => $payload,
                'status' => $response->status()
            ]);

            return [
                'success' => false,
                'message' => 'Gagal mengirim pesan WhatsApp: ' . ($response->json()['error'] ?? $response->json()['message'] ?? 'Unknown Error'),
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('OrbitWA Exception: ' . $e->getMessage(), [
                'payload' => $payload
            ]);
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghubungi API WhatsApp.',
            ];
        }
    }

    /**
     * Send OTP message.
     *
     * @param string $phone
     * @param string $otp
     * @return array
     */
    public function sendOtp(string $phone, string $otp)
    {
        $message = "Kode OTP City Courier Anda adalah: *{$otp}*\n\nJangan sebarkan kode ini kepada siapapun.\n\n_Pesan ini dikirim otomatis oleh sistem City Courier._";
        
        return $this->sendMessage($phone, $message);
    }
}
