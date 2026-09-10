<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class WhatsAppManager implements WhatsAppServiceInterface
{
    protected $provider;
    protected $whatappServices = [];

    public function __construct()
    {
        $this->provider = config('services.whatsapp.provider', 'auto');
    }

    /**
     * Get the appropriate WhatsApp service based on configuration
     */
    public function getService(): WhatsAppServiceInterface
    {
        // If already resolved, return cached instance
        if (isset($this->whatappServices[$this->provider])) {
            return $this->whatappServices[$this->provider];
        }

        // Determine provider
        $provider = $this->determineProvider();

        // Create and cache the service
        $service = $this->createService($provider);
        $this->whatappServices[$provider] = $service;

        return $service;
    }

    /**
     * Send message using the configured WhatsApp service
     */
    public function sendMessage(string $to, string $message): array
    {
        return $this->getService()->sendMessage($to, $message);
    }

    /**
     * Send OTP using the configured WhatsApp service
     */
    public function sendOtp(string $phone, string $otp): array
    {
        return $this->getService()->sendOtp($phone, $otp);
    }

    /**
     * Determine which provider to use
     */
    protected function determineProvider(): string
    {
        // If explicitly configured
        if ($this->provider !== 'auto') {
            return $this->provider;
        }

        // Try Fonnte first (newer integration)
        $fonnteToken = is_string(\App\Models\Setting::get('fonnte_token', null))
            && \App\Models\Setting::get('fonnte_token', null) !== ''
            ? \App\Models\Setting::get('fonnte_token', null)
            : (config('services.fonnte.token') ?? env('FONNTE_TOKEN'));
        if ($fonnteToken) {
            Log::info('WhatsApp: Using Fonnte provider (token configured)');
            return 'fonnte';
        }

        // Fall back to OrbitWA
        $orbitwaKey = config('services.orbitwa.api_key') ?? env('ORBITWA_API_KEY');
        if ($orbitwaKey) {
            Log::info('WhatsApp: Using OrbitWA provider (token configured)');
            return 'orbitwa';
        }

        Log::warning('WhatsApp: No provider configured. Using mock service.');
        return 'mock';
    }

    /**
     * Create a WhatsApp service instance
     */
    protected function createService(string $provider): WhatsAppServiceInterface
    {
        return match ($provider) {
            'fonnte' => new FonnteWhatsAppService(),
            'orbitwa', 'orbit' => new WhatsAppService(),
            default => new MockWhatsAppService(),
        };
    }
}
