<?php

namespace App\Services;

interface WhatsAppServiceInterface
{
    /**
     * Send a WhatsApp message
     *
     * @param string $to Recipient phone number
     * @param string $message The message content
     * @return array{with success: bool, data?: array, message?: string}
     */
    public function sendMessage(string $to, string $message): array;

    /**
     * Send OTP message
     *
     * @param string $phone Recipient phone number
     * @param string $otp OTP code
     * @return array{with success: bool, data?: array, message?: string}
     */
    public function sendOtp(string $phone, string $otp): array;
}
