<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class RajaOngkirService
{
    protected $apiKey;
    protected $baseUrl;
    protected $accountType;
    protected $provider;
    protected $isSandbox;

    public function __construct()
    {
        $this->apiKey = trim(\App\Models\Setting::get('rajaongkir_api_key', env('RAJAONGKIR_API_KEY', '')));
        $this->accountType = \App\Models\Setting::get('rajaongkir_account_type', env('RAJAONGKIR_ACCOUNT_TYPE', 'starter'));
        $this->provider = \App\Models\Setting::get('rajaongkir_provider', 'rajaongkir');
        $this->isSandbox = (bool) \App\Models\Setting::get('rajaongkir_sandbox', false);
        
        // Base URL based on provider and account type
        if ($this->provider === 'komerce') {
            // New Shipping Cost Platform by Komerce
            $this->baseUrl = "https://rajaongkir.komerce.id/api/v1";
        } else {
            if ($this->accountType === 'starter') {
                $this->baseUrl = 'https://api.rajaongkir.com/starter';
            } elseif ($this->accountType === 'basic') {
                $this->baseUrl = 'https://api.rajaongkir.com/basic';
            } else {
                $this->baseUrl = 'https://pro.rajaongkir.com/api';
            }
        }
    }

    /**
     * Check whether Admin enabled the RajaOngkir / shipping rate provider.
     */
    public static function isEnabled(): bool
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('settings')) {
            return true;
        }

        $raw = \App\Models\Setting::get('rajaongkir_enabled', '1');
        if ($raw === null || $raw === '') {
            return true;
        }

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }

    private function disabledPayload(): array
    {
        return [
            'status' => false,
            'disabled' => true,
            'message' => 'Layanan tarif & wilayah ekspedisi (RajaOngkir) sedang dinonaktifkan oleh Admin.',
        ];
    }

    /**
     * Check if a valid non-placeholder API key is configured.
     */
    public function hasValidKey(): bool
    {
        if (!self::isEnabled()) {
            return false;
        }

        return !empty($this->apiKey) && $this->apiKey !== 'YOUR_API_KEY_HERE';
    }

    /**
     * Get headers for the request.
     */
    public function getProvider()
    {
        return $this->provider;
    }

    private function getHeaders()
    {
        if ($this->provider === 'komerce') {
            return [
                'key' => $this->apiKey,
                'Key' => $this->apiKey,
                'Accept' => 'application/json',
            ];
        }

        return [
            'key' => $this->apiKey,
            'Accept' => 'application/json',
        ];
    }

    /**
     * Get all provinces.
     */
    public function getProvinces()
    {
        if (!self::isEnabled()) {
            return $this->disabledPayload();
        }

        try {
            $url = $this->provider === 'komerce' 
                ? $this->baseUrl . '/destination/province' 
                : $this->baseUrl . '/province';
                
            $response = Http::timeout(5)->withHeaders($this->getHeaders())->get($url);
            return $response->json();
        } catch (\Exception $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get cities by province.
     */
    public function getCities($provinceId = null)
    {
        if (!self::isEnabled()) {
            return $this->disabledPayload();
        }

        try {
            if ($this->provider === 'komerce') {
                $url = $this->baseUrl . '/destination/city';
                if ($provinceId) {
                    $url .= '/' . $provinceId;
                }
            } else {
                $url = $this->baseUrl . '/city';
                if ($provinceId) {
                    $url .= '?province=' . $provinceId;
                }
            }

            $response = Http::timeout(5)->withHeaders($this->getHeaders())->get($url);
            return $response->json();
        } catch (\Exception $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Calculate shipping cost.
     * 
     * @param int $origin City ID
     * @param int $destination City ID
     * @param int $weight Weight in grams
     * @param string $courier jne, pos, tiki
     */
    public function calculateCost($origin, $destination, $weight, $courier)
    {
        if (!self::isEnabled()) {
            return $this->disabledPayload();
        }

        try {
            if ($this->provider === 'komerce') {
                $url = $this->baseUrl . '/calculate/district/domestic-cost';
                $data = [
                    'origin' => $origin,
                    'destination' => $destination,
                    'weight' => $weight,
                    'courier' => $courier,
                    'price' => 'lowest'
                ];
                
                $response = Http::timeout(5)->withHeaders($this->getHeaders())
                    ->asForm()
                    ->post($url, $data);
            } else {
                $response = Http::timeout(5)->withHeaders($this->getHeaders())->post($this->baseUrl . '/cost', [
                    'origin' => $origin,
                    'destination' => $destination,
                    'weight' => $weight,
                    'courier' => $courier
                ]);
            }

            return $response->json();
        } catch (\Exception $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get districts (subdistricts) by city.
     */
    public function getDistricts($cityId)
    {
        if (!self::isEnabled()) {
            return $this->disabledPayload();
        }

        try {
            if ($this->provider === 'komerce') {
                $url = $this->baseUrl . '/destination/district/' . $cityId;
            } else {
                $url = $this->baseUrl . '/subdistrict?city=' . $cityId;
            }

            $response = Http::timeout(5)->withHeaders($this->getHeaders())->get($url);
            return $response->json();
        } catch (\Exception $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get subdistricts (kelurahan) by district (kecamatan).
     * Only for Komerce.
     */
    public function getSubdistricts($districtId)
    {
        if (!self::isEnabled()) {
            return $this->disabledPayload();
        }

        try {
            if ($this->provider === 'komerce') {
                $url = $this->baseUrl . '/destination/sub-district/' . $districtId;
                $response = Http::timeout(5)->withHeaders($this->getHeaders())->get($url);
                return $response->json();
            }

            return ['status' => false, 'message' => 'Not supported by this provider'];
        } catch (\Exception $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function searchDestination($keyword)
    {
        if (!self::isEnabled()) {
            return $this->disabledPayload();
        }

        try {
            if ($this->provider === 'komerce') {
                $url = $this->baseUrl . '/destination/domestic-destination?search=' . urlencode($keyword);
                $response = Http::timeout(5)->withHeaders($this->getHeaders())->get($url);
                return $response->json();
            }
            
            return ['status' => false, 'message' => 'Pencarian hanya didukung untuk provider Komerce.'];
        } catch (\Exception $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }
}
