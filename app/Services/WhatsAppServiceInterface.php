<?php

namespace App\Services;

interface WhatsAppServiceInterface
{
    /**
     * Send a WhatsApp message
     *
     * @param string $to Recipient phone number
     * @param string $message The message content
     * @param string|null $mediaUrl Optional media/image URL (e.g. for QR Code)
     * @return array{with success: bool, data?: array, message?: string}
     */
    public function sendMessage(string $to, string $message, ?string $mediaUrl = null): array;

    /**
     * Send OTP message
     *
     * @param string $phone Recipient phone number
     * @param string $otp OTP code
     * @return array{with success: bool, data?: array, message?: string}
     */
    public function sendOtp(string $phone, string $otp): array;
}
