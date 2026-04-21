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
        $this->deviceId = \App\Models\Setting::get('orbitwa_device_id', env('ORBITWA_DEVICE_ID'));
    }

    /**
     * Send a WhatsApp message.
     *
     * @param string $to Recipient phone number (format: 628123456789)
     * @param string $message The message content
     * @return array
     */
    public function sendMessage(string $to, string $message)
    {
        if (!$this->apiKey) {
            Log::error('OrbitWA API Key is not set.');
            return [
                'success' => false,
                'message' => 'WhatsApp API Key is not configured.'
            ];
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->post($this->baseUrl . '/messages/send', [
                    'device_id' => $this->deviceId,
                    'to' => $to,
                    'message' => $message,
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            Log::error('OrbitWA API Error: ' . $response->body());

            return [
                'success' => false,
                'message' => 'Gagal mengirim pesan WhatsApp: ' . ($response->json()['message'] ?? 'Unknown Error'),
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('OrbitWA Exception: ' . $e->getMessage());
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
        
        // Ensure phone number format is correct (starting with 62 instead of 0 or +)
        $formattedPhone = $this->formatPhoneNumber($phone);
        
        return $this->sendMessage($formattedPhone, $message);
    }

    /**
     * Format phone number to international format without + (e.g., 62812...)
     *
     * @param string $phone
     * @return string
     */
    protected function formatPhoneNumber(string $phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        } elseif (!str_starts_with($phone, '62')) {
            // Defaulting to 62 if no prefix is found, assuming Indonesian numbers
            $phone = '62' . $phone;
        }
        
        return $phone;
    }
}
