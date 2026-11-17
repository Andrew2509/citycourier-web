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

    public function doku(): array
    {
        $sandboxUrl = $this->readPlain('doku_sandbox_url', (string) config('payment_wallet.doku.sandbox_url', 'https://api-sandbox.doku.com'));
        $productionUrl = $this->readPlain('doku_production_url', (string) config('payment_wallet.doku.production_url', 'https://api.doku.com'));
        $clientId = $this->readPlain('doku_client_id', (string) config('payment_wallet.doku.client_id', ''));
        $secretKey = $this->readSecret('doku_secret_key', (string) config('payment_wallet.doku.secret_key', ''));

        $configured = trim($clientId) !== '' && trim($secretKey) !== '';

        return [
            'enabled' => $this->isEnabled('doku', true),
            'sandbox_url' => rtrim($sandboxUrl ?: 'https://api-sandbox.doku.com', '/'),
            'production_url' => rtrim($productionUrl ?: 'https://api.doku.com', '/'),
            'client_id' => trim($clientId),
            'secret_key' => trim($secretKey),
            'timeout_seconds' => (int) config('payment_wallet.doku.timeout_seconds', 30),
            'configured' => $configured,
            'secret_masked' => $this->maskSecret($secretKey),
            'source' => $this->hasDbSetting('doku_secret_key') ? 'database' : ($configured ? 'env' : 'none'),
        ];
    }

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
