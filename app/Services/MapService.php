<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MapService
{
    protected $baseUrl;
    protected $apiKey;
    protected $provider;

    public function __construct()
    {
        // Ambil konfigurasi dari Database Settings (Admin Panel) atau fallback ke .env/config
        $this->provider = Setting::get('map_provider', env('MAP_PROVIDER', 'maplibre'));
        $this->baseUrl = Setting::get('map_base_url', env('MAP_BASE_URL', 'https://demotiles.maplibre.org'));
        $this->apiKey = Setting::get('map_api_key', env('MAP_API_KEY', ''));
    }

    /**
     * Get active provider name.
     */
    public function getProvider()
    {
        return $this->provider;
    }

    /**
     * Get Route (Directions API) between origin and destination with optional via points.
     *
     * @param string $origin Format: "lng,lat"
     * @param string $destination Format: "lng,lat"
     * @param array $vias Array of coordinates "lng,lat"
     * @return array
     */
    public function getRoute($origin, $destination, $vias = [])
    {
        try {
            $coordinates = $origin;
            if (!empty($vias)) {
                $coordinates .= ';' . implode(';', $vias);
            }
            $coordinates .= ';' . $destination;

            // Mapbox Directions API format: /directions/v5/mapbox/driving/{coordinates}
            $url = "{$this->baseUrl}/directions/v5/mapbox/driving/{$coordinates}";

            $response = Http::get($url, [
                'geometries' => 'geojson',
                'overview' => 'full',
                'steps' => 'true',
                'access_token' => $this->apiKey
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            Log::error('MapService Directions Error: ' . $response->body());
            return [
                'success' => false,
                'message' => 'Gagal mengambil data rute dari penyedia peta.',
                'error' => $response->json()
            ];
        } catch (\Exception $e) {
            Log::error('MapService Directions Exception: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan sistem saat mengambil data rute: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get Matrix (Distance & Duration Matrix API) between multiple origins and destinations.
     *
     * @param array $coordinates Array of "lng,lat"
     * @return array
     */
    public function getMatrix($coordinates)
    {
        try {
            $coordString = implode(';', $coordinates);
            // Mapbox Matrix API format: /distances/v1/mapbox/driving/{coordinates}
            $url = "{$this->baseUrl}/distances/v1/mapbox/driving/{$coordString}";

            $response = Http::get($url, [
                'annotations' => 'distance,duration',
                'access_token' => $this->apiKey
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            Log::error('MapService Matrix Error: ' . $response->body());
            return [
                'success' => false,
                'message' => 'Gagal mengambil matriks jarak dari penyedia peta.',
                'error' => $response->json()
            ];
        } catch (\Exception $e) {
            Log::error('MapService Matrix Exception: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan sistem saat mengambil matriks jarak: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Search POI (Geocoding / Search API) by keyword.
     *
     * @param string $keyword
     * @param string|null $proximity Format "lng,lat"
     * @return array
     */
    public function searchPOI($keyword, $proximity = null)
    {
        try {
            // Mapbox Temporary Geocoding API format
            $url = "{$this->baseUrl}/geocoding/v5/mapbox.places/" . urlencode($keyword) . ".json";

            $params = [
                'access_token' => $this->apiKey,
                'limit' => 10,
                'country' => 'ID' // Batasi hanya di Indonesia
            ];

            if ($proximity) {
                $params['proximity'] = $proximity;
            }

            $response = Http::get($url, $params);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            Log::error('MapService Search Error: ' . $response->body());
            return [
                'success' => false,
                'message' => 'Gagal mencari lokasi dari penyedia peta.',
                'error' => $response->json()
            ];
        } catch (\Exception $e) {
            Log::error('MapService Search Exception: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan sistem saat mencari lokasi: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get Style Content from provider.
     *
     * @param string $name
     * @return array
     */
    public function getStyleContent($name)
    {
        try {
            if ($this->provider === 'maplibre') {
                $url = "{$this->baseUrl}/style.json";
            } else {
                $url = "{$this->baseUrl}/maps/{$name}/style.json";
            }

            $response = Http::get($url, [
                'key' => $this->apiKey,
                'access_token' => $this->apiKey
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            Log::error("MapService getStyleContent Error: " . $response->body());
            return [
                'success' => false,
                'message' => 'Gagal mengambil konfigurasi style dari server peta.'
            ];
        } catch (\Exception $e) {
            Log::error("MapService getStyleContent Exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan eksternal: ' . $e->getMessage()
            ];
        }
    }
}
