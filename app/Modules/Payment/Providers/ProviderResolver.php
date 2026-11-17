<?php

namespace App\Modules\Payment\Providers;

use App\Models\Setting;
use App\Modules\Payment\Contracts\PaymentProviderInterface;
use App\Modules\Payment\Contracts\PayoutProviderInterface;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ProviderResolver
{
    public function __construct(
        private readonly MockPaymentProvider $mock,
        private readonly XenditPaymentProvider $xenditPayment,
        private readonly XenditPayoutProvider $xenditPayout,
        private readonly DokuPaymentProvider $dokuPayment
    ) {}

    public function currentMode(): string
    {
        $configured = (string) config('payment_wallet.mode', 'sandbox');

        if (Schema::hasTable('settings')) {
            $dbMode = Setting::get('payment_wallet_mode');
            if (is_string($dbMode) && in_array($dbMode, ['demo', 'sandbox', 'production'], true)) {
                return $dbMode;
            }
        }

        return in_array($configured, ['demo', 'sandbox', 'production'], true) ? $configured : 'sandbox';
    }

    public function isCustomerWithdrawalEnabled(): bool
    {
        $mode = $this->currentMode();
        if ($mode === 'production') {
            return (bool) config('payment_wallet.customer_withdrawal_regulatory_approved', false);
        }

        if (Schema::hasTable('settings')) {
            $override = Setting::get('customer_withdrawal_enabled_in_sandbox');
            if ($override !== null) {
                return filter_var($override, FILTER_VALIDATE_BOOLEAN);
            }
        }

        return (bool) config('payment_wallet.customer_withdrawal_enabled_in_sandbox', true);
    }

    public function activePaymentProviderCode(): string
    {
        $mode = $this->currentMode();
        if ($mode === 'demo') {
            return 'mock';
        }

        $code = (string) config('payment_wallet.active_payment_provider', 'xendit');
        if (Schema::hasTable('settings')) {
            $dbCode = Setting::get('payment_active_provider');
            if (is_string($dbCode) && $dbCode !== '') {
                $code = $dbCode;
            }
        }

        return strtolower($code);
    }

    public function activePayoutProviderCode(): string
    {
        $mode = $this->currentMode();
        if ($mode === 'demo') {
            return 'mock';
        }

        $code = (string) config('payment_wallet.active_payout_provider', 'xendit');
        if (Schema::hasTable('settings')) {
            $dbCode = Setting::get('payout_active_provider');
            if (is_string($dbCode) && $dbCode !== '') {
                $code = $dbCode;
            }
        }

        return strtolower($code);
    }

    public function resolvePaymentProvider(?string $forcedCode = null): PaymentProviderInterface
    {
        $mode = $this->currentMode();
        $code = $forcedCode ? strtolower($forcedCode) : $this->activePaymentProviderCode();

        if ($this->isPaymentProviderDisabled($code)) {
            $label = $this->providerDisplayName($code);
            throw new RuntimeException("PROVIDER_DISABLED:Gateway pembayaran {$label} sedang dinonaktifkan oleh Admin.");
        }

        if ($mode === 'production') {
            $provider = match ($code) {
                'doku' => $this->dokuPayment,
                'xendit' => $this->xenditPayment,
                default => throw new RuntimeException("Provider '{$code}' tidak diizinkan pada mode production."),
            };

            if (! $provider->isConfigured()) {
                throw new RuntimeException("Kredensial provider '{$code}' belum dikonfigurasi untuk mode production.");
            }

            return $provider;
        }

        if ($mode === 'demo' || $code === 'mock') {
            return $this->mock;
        }

        $provider = match ($code) {
            'doku' => $this->dokuPayment,
            'xendit' => $this->xenditPayment,
            default => $this->mock,
        };

        return $provider->isConfigured() ? $provider : $this->mock;
    }

    public function isPaymentProviderDisabled(?string $forcedCode = null): bool
    {
        $mode = $this->currentMode();
        $code = $forcedCode ? strtolower($forcedCode) : $this->activePaymentProviderCode();
        if ($mode === 'demo' || $code === 'mock') {
            return false;
        }

        return in_array($code, ['xendit', 'doku'], true)
            && app(\App\Modules\Payment\Services\GatewayCredentialsResolver::class)->isExplicitlyDisabled($code);
    }

    public function isPayoutProviderDisabled(?string $forcedCode = null): bool
    {
        $mode = $this->currentMode();
        $code = $forcedCode ? strtolower($forcedCode) : $this->activePayoutProviderCode();
        if ($mode === 'demo' || $code === 'mock') {
            return false;
        }

        return in_array($code, ['xendit', 'doku', 'ipaymu', 'dana', 'ovo'], true)
            && app(\App\Modules\Payment\Services\GatewayCredentialsResolver::class)->isExplicitlyDisabled($code);
    }

    public function providerDisplayName(string $code): string
    {
        return match (strtolower($code)) {
            'xendit' => 'Xendit',
            'doku' => 'DOKU',
            'ipaymu' => 'iPaymu',
            'dana' => 'DANA',
            'ovo' => 'OVO',
            default => strtoupper($code),
        };
    }

    public function safePaymentProviderCode(?string $forcedCode = null): string
    {
        $mode = $this->currentMode();
        $code = $forcedCode ? strtolower($forcedCode) : $this->activePaymentProviderCode();
        if ($mode === 'demo' || $code === 'mock') {
            return 'mock';
        }
        if ($this->isPaymentProviderDisabled($code)) {
            return $code;
        }

        return $this->resolvePaymentProvider($code)->providerCode();
    }

    public function resolvePayoutProvider(?string $forcedCode = null): PayoutProviderInterface
    {
        $mode = $this->currentMode();
        $code = $forcedCode ? strtolower($forcedCode) : $this->activePayoutProviderCode();

        if ($this->isPayoutProviderDisabled($code)) {
            $label = $this->providerDisplayName($code);
            throw new RuntimeException("PROVIDER_DISABLED:Gateway pencairan {$label} sedang dinonaktifkan oleh Admin.");
        }

        if ($mode === 'production') {
            $provider = match ($code) {
                'xendit' => $this->xenditPayout,
                default => throw new RuntimeException("Payout provider '{$code}' tidak diizinkan pada mode production."),
            };

            if (! $provider->isConfigured()) {
                throw new RuntimeException("Kredensial payout provider '{$code}' belum dikonfigurasi.");
            }

            return $provider;
        }

        if ($mode === 'demo' || $code === 'mock') {
            return $this->mock;
        }

        return $this->xenditPayout->isConfigured() ? $this->xenditPayout : $this->mock;
    }

    public function resolveWebhookPaymentProvider(string $providerCode): PaymentProviderInterface
    {
        return match (strtolower($providerCode)) {
            'xendit' => $this->xenditPayment,
            'doku' => $this->dokuPayment,
            default => $this->mock,
        };
    }

    public function resolveWebhookPayoutProvider(string $providerCode): PayoutProviderInterface
    {
        return match (strtolower($providerCode)) {
            'xendit' => $this->xenditPayout,
            default => $this->mock,
        };
    }
}
