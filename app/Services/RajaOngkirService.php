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
        $this->apiKey = \App\Models\Setting::get('rajaongkir_api_key', env('RAJAONGKIR_API_KEY', ''));
        $this->accountType = \App\Models\Setting::get('rajaongkir_account_type', env('RAJAONGKIR_ACCOUNT_TYPE', 'starter'));
        $this->provider = \App\Models\Setting::get('rajaongkir_provider', 'rajaongkir');
        $this->isSandbox = \App\Models\Setting::get('rajaongkir_sandbox', false);
        
        // Base URL based on provider and account type
        if ($this->provider === 'komerce') {
            $subdomain = $this->isSandbox ? 'api-sandbox' : 'api';
            $this->baseUrl = "https://{$subdomain}.collaborator.komerce.id/tariff/api/v1";
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
     * Get headers for the request.
     */
    protected function getHeaders()
    {
        if ($this->provider === 'komerce') {
            return [
                'x-api-key' => $this->apiKey,
                'key' => $this->apiKey, // Some Komerce endpoints still use 'key'
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
        $url = $this->provider === 'komerce' 
            ? $this->baseUrl . '/destination/province' 
            : $this->baseUrl . '/province';
            
        $response = Http::withHeaders($this->getHeaders())->get($url);
        return $response->json();
    }

    /**
     * Get cities by province.
     */
    public function getCities($provinceId = null)
    {
        if ($this->provider === 'komerce') {
            $url = $this->baseUrl . '/destination/city';
            if ($provinceId) {
                $url .= '?province=' . $provinceId;
            }
        } else {
            $url = $this->baseUrl . '/city';
            if ($provinceId) {
                $url .= '?province=' . $provinceId;
            }
        }

        $response = Http::withHeaders($this->getHeaders())->get($url);

        return $response->json();
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
        if ($this->provider === 'komerce') {
            $url = $this->baseUrl . '/calculate/domestic-cost';
            $data = [
                'origin' => $origin,
                'destination' => $destination,
                'weight' => $weight,
                'courier' => $courier
            ];
            
            $response = Http::withHeaders($this->getHeaders())
                ->asForm()
                ->post($url, $data);
        } else {
            $response = Http::withHeaders($this->getHeaders())->post($this->baseUrl . '/cost', [
                'origin' => $origin,
                'destination' => $destination,
                'weight' => $weight,
                'courier' => $courier
            ]);
        }

        return $response->json();
    }

    /**
     * Get districts (subdistricts) by city.
     */
    public function getDistricts($cityId)
    {
        if ($this->provider === 'komerce') {
            $url = $this->baseUrl . '/destination/district?city=' . $cityId;
        } else {
            $url = $this->baseUrl . '/subdistrict?city=' . $cityId;
        }

        $response = Http::withHeaders($this->getHeaders())->get($url);

        return $response->json();
    }

    /**
     * Get subdistricts (kelurahan) by district (kecamatan).
     * Only for Komerce.
     */
    public function getSubdistricts($districtId)
    {
        if ($this->provider === 'komerce') {
            $url = $this->baseUrl . '/destination/sub-district?district=' . $districtId;
            $response = Http::withHeaders($this->getHeaders())->get($url);
            return $response->json();
        }

        return ['status' => false, 'message' => 'Not supported by this provider'];
    }
}
