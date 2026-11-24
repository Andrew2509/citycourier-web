<?php

namespace App\Modules\Payment\Services;

use App\Models\Setting;
use App\Modules\Payment\Models\PaymentProviderConfig;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

class GatewayCredentialsResolver
{
    private const ENC_PREFIX = 'enc:';

    public function xendit(): array
    {
        $baseUrl = $this->readPlain('xendit_base_url', (string) config('payment_wallet.xendit.base_url', 'https://api.xendit.co'));
        $publicKey = $this->readPlain('xendit_public_key', (string) config('payment_wallet.xendit.public_key', ''));
        $secretKey = $this->readSecret('xendit_secret_key', (string) config('payment_wallet.xendit.secret_key', ''));
        $callbackToken = $this->readSecret('xendit_callback_token', (string) config('payment_wallet.xendit.callback_token', ''));

        $configured = trim($secretKey) !== '';
        $isSandbox = $configured
            ? str_starts_with($secretKey, 'xnd_development_')
            : ((string) $this->readPlain('payment_wallet_mode', (string) config('payment_wallet.mode', 'sandbox')) !== 'production');

        return [
            'enabled' => $this->isEnabled('xendit', true),
            'base_url' => rtrim($baseUrl ?: 'https://api.xendit.co', '/'),
            'public_key' => trim($publicKey),
            'secret_key' => trim($secretKey),
            'callback_token' => trim($callbackToken),
            'timeout_seconds' => (int) config('payment_wallet.xendit.timeout_seconds', 30),
            'api_version' => (string) config('payment_wallet.xendit.api_version', '2024-11-11'),
            'configured' => $configured,
            'has_callback_token' => trim($callbackToken) !== '',
            'is_sandbox' => $isSandbox,
            'secret_masked' => $this->maskSecret($secretKey),
            'callback_masked' => $this->maskSecret($callbackToken),
            'source' => $this->hasDbSetting('xendit_secret_key') ? 'database' : ($configured ? 'env' : 'none'),
        ];
    }

    public function tomtom(): array
    {
        $baseUrl = $this->readPlain('tomtom_base_url', (string) config('services.tomtom.base_url', 'https://api.tomtom.com'));
        $apiKey = $this->readSecret('tomtom_api_key', (string) config('services.tomtom.api_key', ''));
        $timeout = (int) $this->readPlain('tomtom_timeout_seconds', (string) config('services.tomtom.timeout_seconds', 10));
        $cacheTtl = (int) $this->readPlain('tomtom_cache_ttl_seconds', (string) config('services.tomtom.flow_cache_ttl_seconds', 60));

        $configured = trim($apiKey) !== '';

        return [
            'enabled' => $this->isEnabled('tomtom', true),
            'base_url' => rtrim($baseUrl ?: 'https://api.tomtom.com', '/'),
            'api_key' => trim($apiKey),
            'api_key_masked' => $this->maskSecret($apiKey),
            'configured' => $configured,
            'timeout_seconds' => max(3, min(20, $timeout ?: 10)),
            'cache_ttl_seconds' => max(15, min(300, $cacheTtl ?: 60)),
            'has_db_cache_ttl' => $this->hasDbSetting('tomtom_cache_ttl_seconds'),
            'source' => $this->hasDbSetting('tomtom_api_key') ? 'database' : ($configured ? 'env' : 'none'),
        ];
    }

    /**
     * Default ON/OFF state for Admin-managed active external providers.
     * DOKU has been removed completely; Xendit is the sole active payment & payout provider.
     *
     * @var array<string, bool>
     */
    public const PROVIDER_DEFAULTS = [
        'whatsapp' => true,
        'map' => true,
        'rajaongkir' => true,
        'xendit' => true,
        'tomtom' => true,
    ];

    public function isEnabled(string $providerCode, bool $default = true): bool
    {
        if (! Schema::hasTable('settings')) {
            return $default;
        }

        $raw = Setting::get("{$providerCode}_enabled");
        if ($raw === null || $raw === '') {
            return $default;
        }

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Check whether an external provider is active using its canonical Admin default.
     */
    public function isProviderEnabled(string $providerCode): bool
    {
        $code = strtolower($providerCode);
        $default = self::PROVIDER_DEFAULTS[$code] ?? ($code === 'ovo' ? false : true);

        return $this->isEnabled($code, $default);
    }

    /**
     * Check whether a provider was explicitly disabled via Admin toggle ('0' / false).
     */
    public function isExplicitlyDisabled(string $providerCode): bool
    {
        if (! Schema::hasTable('settings')) {
            return false;
        }

        $code = strtolower($providerCode);
        $raw = Setting::get("{$code}_enabled");
        if ($raw === null || $raw === '') {
            return false;
        }

        return ! filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Return ON/OFF states for all 8 Admin-managed external providers.
     *
     * @return array<string, bool>
     */
    public function allProviderStates(): array
    {
        $states = [];
        foreach (self::PROVIDER_DEFAULTS as $code => $default) {
            $states[$code] = $this->isEnabled($code, $default);
        }

        return $states;
    }

    public function saveEncryptedSecret(string $key, ?string $plainValue, string $group): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $trimmed = trim((string) $plainValue);
        if ($trimmed === '') {
            return;
        }

        Setting::set($key, self::ENC_PREFIX.Crypt::encryptString($trimmed), $group);
    }

    public function syncProviderConfigRecord(
        string $providerCode,
        string $displayName,
        string $mode,
        bool $isActive,
        bool $supportsMoneyIn,
        bool $supportsMoneyOut,
        array $nonSecretSettings = [],
        ?string $healthStatus = null
    ): void {
        if (! Schema::hasTable('payment_provider_configs')) {
            return;
        }

        $payload = [
            'display_name' => $displayName,
            'mode' => $mode,
            'is_active' => $isActive,
            'supports_money_in' => $supportsMoneyIn,
            'supports_money_out' => $supportsMoneyOut,
            'supports_customer_withdrawal' => false,
            'settings' => $nonSecretSettings,
        ];

        if ($healthStatus !== null) {
            $payload['health_status'] = $healthStatus;
            $payload['last_tested_at'] = now();
        }

        PaymentProviderConfig::updateOrCreate(
            ['provider_code' => $providerCode],
            $payload
        );
    }

    public function maskSecret(?string $value): string
    {
        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return '';
        }

        $len = strlen($trimmed);
        if ($len <= 8) {
            return str_repeat('*', $len);
        }

        return substr($trimmed, 0, 6).'••••••'.substr($trimmed, -4);
    }

    private function readPlain(string $key, string $fallback = ''): string
    {
        if (! Schema::hasTable('settings')) {
            return $fallback;
        }

        $val = Setting::get($key);
        if (is_string($val) && trim($val) !== '') {
            return trim($val);
        }

        return $fallback;
    }

    private function readSecret(string $key, string $fallback = ''): string
    {
        if (! Schema::hasTable('settings')) {
            return $fallback;
        }

        $raw = Setting::get($key);
        if (! is_string($raw) || trim($raw) === '') {
            return $fallback;
        }

        if (str_starts_with($raw, self::ENC_PREFIX)) {
            try {
                return Crypt::decryptString(substr($raw, strlen(self::ENC_PREFIX)));
            } catch (\Throwable) {
                return $fallback;
            }
        }

        return trim($raw);
    }

    private function hasDbSetting(string $key): bool
    {
        if (! Schema::hasTable('settings')) {
            return false;
        }

        $val = Setting::get($key);

        return is_string($val) && trim($val) !== '';
    }
}
