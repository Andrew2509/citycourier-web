<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonnteWhatsAppService implements WhatsAppServiceInterface
{
    protected $token;
    protected $baseUrl;
    protected $sendNumber;

    public function __construct()
    {
        $this->token = \App\Models\Setting::get('fonnte_token', config('services.fonnte.token') ?? env('FONNTE_TOKEN'));
        $this->baseUrl = config('services.fonnte.base_url') ?? env('FONNTE_BASE_URL', 'https://api.fonnte.com');
        $this->sendNumber = \App\Models\Setting::get('fonnte_send_number', config('services.fonnte.send_number') ?? env('FONNTE_SEND_NUMBER', ''));
    }

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

    /**
     * Send a WhatsApp message via Fonnte API.
     *
     * @param string $to Recipient phone number (format: 628123456789)
     * @param string $message The message content
     * @return array
     */
    public function sendMessage(string $to, string $message): array
    {
        $to = $this->formatPhoneNumber($to);

        if (!$this->token) {
            Log::error('Fonnte Token is not set.');
            return [
                'success' => false,
                'message' => 'Fonnte API Token is not configured.',
            ];
        }

        $payload = [
            'to' => $to,
            'message' => $message,
        ];

        // Add send number if configured
        if ($this->sendNumber) {
            $payload['send_number'] = $this->sendNumber;
        }

        try {
            $response = Http::withToken($this->token)
                ->timeout(15)
                ->post($this->baseUrl . '/send', $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                // Fonnte response structure check
                if (isset($data['status']) && $data['status'] === 'success') {
                    return [
                        'success' => true,
                        'data' => $data,
                    ];
                }
                
                // Some endpoints return 200 with different structure
                if (isset($data['success']) && $data['success'] === true) {
                    return [
                        'success' => true,
                        'data' => $data,
                    ];
                }

                return [
                    'success' => false,
                    'message' => $data['description'] ?? 'Gagal mengirim pesan WhatsApp.',
                    'status' => $response->status(),
                ];
            }

            Log::error('Fonnte API Error: ' . $response->body(), [
                'payload' => $payload,
                'status' => $response->status(),
            ]);

            $json = $response->json();
            $errorMessage = $json['description'] ?? $json['message'] ?? $json['error'] ?? 'Server error (' . $response->status() . ')';

            return [
                'success' => false,
                'message' => 'Gagal mengirim pesan WhatsApp via Fonnte: ' . $errorMessage,
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('Fonnte Exception: ' . $e->getMessage(), [
                'payload' => $payload,
            ]);
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghubungi API Fonnte: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Send OTP message via WhatsApp.
     *
     * @param string $phone
     * @param string $otp
     * @return array
     */
    public function sendOtp(string $phone, string $otp): array
    {
        $message = "Kode OTP City Courier Anda adalah: *{$otp}*\n\nJangan sebarkan kode ini kepada siapapun.\n\n_Pesan ini dikirim otomatis oleh sistem City Courier._";
        
        return $this->sendMessage($phone, $message);
    }
}
