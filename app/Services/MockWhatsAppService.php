<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class MockWhatsAppService implements WhatsAppServiceInterface
{
    /**
     * Format phone number to international format (62...)
     */
    protected function formatPhoneNumber(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($cleaned, '0')) {
            $cleaned = '62' . substr($cleaned, 1);
        }
        if (!str_starts_with($cleaned, '62')) {
            $cleaned = '62' . $cleaned;
        }
        return $cleaned;
    }

    /**
     * Send a WhatsApp message (mock - logs only)
     */
    public function sendMessage(string $to, string $message): array
    {
        $to = $this->formatPhoneNumber($to);
        
        Log::info('[MOCK WhatsApp] Message sent', [
            'to' => $to,
            'message' => $message,
        ]);
        
        return [
            'success' => true,
            'data' => [
                'status' => 'success',
                'message' => 'Mock: Message would be sent to ' . $to,
                'mock' => true,
            ],
        ];
    }

    /**
     * Send OTP message (mock - logs only)
     */
    public function sendOtp(string $phone, string $otp): array
    {
        $message = "Kode OTP City Courier Anda adalah: *{$otp}*\n\nJangan sebarkan kode ini kepada siapapun.\n\n_Pesan ini dikirim otomatis oleh sistem City Courier._";
        
        return $this->sendMessage($phone, $message);
    }
}
