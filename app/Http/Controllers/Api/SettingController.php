<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Payment\Providers\ProviderResolver;
use App\Modules\Payment\Services\GatewayCredentialsResolver;

class SettingController extends Controller
{
    public function index(
        GatewayCredentialsResolver $credentialsResolver,
        ProviderResolver $providerResolver
    ) {
        $maxDistance = 2000;
        $providers = $credentialsResolver->allProviderStates();
        $activeTopupProvider = $providerResolver->activePaymentProviderCode();
        $topupEnabled = $providerResolver->currentMode() === 'demo'
            || $activeTopupProvider === 'mock'
            || ($providers[$activeTopupProvider] ?? true);

        return response()->json([
            'success' => true,
            'data' => [
                'courier_max_distance_meters' => (int) $maxDistance,
                'providers' => $providers,
                'active_payment_provider' => $activeTopupProvider,
                'topup_provider_enabled' => $topupEnabled,
            ],
        ]);
    }
}
