<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class RajaOngkirService
{
    protected $apiKey;
    protected $baseUrl;
    protected $accountType;

    public function __construct()
    {
        $this->apiKey = \App\Models\Setting::get('rajaongkir_api_key', env('RAJAONGKIR_API_KEY', ''));
        $this->accountType = \App\Models\Setting::get('rajaongkir_account_type', env('RAJAONGKIR_ACCOUNT_TYPE', 'starter'));
        
        // Base URL based on account type
        if ($this->accountType === 'starter') {
            $this->baseUrl = 'https://api.rajaongkir.com/starter';
        } elseif ($this->accountType === 'basic') {
            $this->baseUrl = 'https://api.rajaongkir.com/basic';
        } else {
            $this->baseUrl = 'https://pro.rajaongkir.com/api';
        }
    }

    /**
     * Get all provinces.
     */
    public function getProvinces()
    {
        $response = Http::withHeaders([
            'key' => $this->apiKey
        ])->get($this->baseUrl . '/province');

        return $response->json();
    }

    /**
     * Get cities by province.
     */
    public function getCities($provinceId = null)
    {
        $url = $this->baseUrl . '/city';
        if ($provinceId) {
            $url .= '?province=' . $provinceId;
        }

        $response = Http::withHeaders([
            'key' => $this->apiKey
        ])->get($url);

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
        $response = Http::withHeaders([
            'key' => $this->apiKey
        ])->post($this->baseUrl . '/cost', [
            'origin' => $origin,
            'destination' => $destination,
            'weight' => $weight,
            'courier' => $courier
        ]);

        return $response->json();
    }
}
