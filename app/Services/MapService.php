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
        $this->provider = Setting::get('map_provider', env('MAP_PROVIDER', 'osrm'));
        $this->baseUrl = Setting::get('map_base_url', env('MAP_BASE_URL', self::defaultBaseUrl($this->provider)));
        $this->apiKey = Setting::get('map_api_key', env('MAP_API_KEY', ''));
    }

    /**
     * Base URL bawaan sesuai provider.
     *
     * - 'osrm'       → public OSRM demo server (OpenStreetMap), tanpa API key.
     * - 'maplibre'   → demo Maplibre (hanya style/tile).
     * - 'mapbox'     → api.mapbox.com.
     */
    public static function defaultBaseUrl($provider)
    {
        switch ($provider) {
            case 'osrm':
                return 'https://router.project-osrm.org';
            case 'mapbox':
                return 'https://api.mapbox.com';
            case 'google':
                return 'https://maps.googleapis.com';
            default:
                return 'https://demotiles.maplibre.org';
        }
    }

    /**
     * Base URL efektif untuk request sesuai provider aktif.
     */
    private function endpoint()
    {
        $base = rtrim((string) $this->baseUrl, '/');
        if ($this->provider === 'osrm') {
            if ($base === '' || str_contains($base, 'maplibre.org') || str_contains($base, 'api.mapbox.com')) {
                return 'https://router.project-osrm.org';
            }
            return $base;
        }
        return $base !== '' ? $base : self::defaultBaseUrl($this->provider);
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

            // OSRM (OpenStreetMap) — open source, tanpa API key.
            if ($this->provider === 'osrm') {
                return $this->osrmRoute($coordinates);
            }

            // Mapbox Directions API format: /directions/v5/mapbox/driving/{coordinates}
            $url = "{$this->endpoint()}/directions/v5/mapbox/driving/{$coordinates}";

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
     * Ambil rute dari OSRM Directions API.
     *
     * Format respons identik dengan Mapbox v5 (routes > geometry, distance,
     * duration, steps + maneuver), sehingga parser Flutter tetap berfungsi.
     */
    private function osrmRoute($coordinates)
    {
        $url = $this->endpoint() . '/route/v1/driving/' . $coordinates;

        $response = Http::get($url, [
            'overview' => 'full',
            'geometries' => 'geojson',
            'steps' => 'true',
        ]);

        if ($response->successful()) {
            $data = $response->json();
            if (!is_array($data) || ($data['code'] ?? '') !== 'Ok') {
                Log::error('MapService OSRM Directions Error: ' . json_encode($data));
                return [
                    'success' => false,
                    'message' => 'Gagal mengambil data rute dari OpenStreetMap: ' . ($data['message'] ?? 'Rute tidak ditemukan.'),
                    'error' => $data,
                ];
            }
            return [
                'success' => true,
                'data' => $data
            ];
        }

        Log::error('MapService OSRM Directions Error: ' . $response->body());
        return [
            'success' => false,
            'message' => 'Gagal mengambil data rute dari OpenStreetMap.',
            'error' => $response->json()
        ];
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

            // OSRM Table API (ada format serupa Mapbox matrix).
            if ($this->provider === 'osrm') {
                return $this->osrmMatrix($coordString);
            }

            // Mapbox Matrix API format: /distances/v1/mapbox/driving/{coordinates}
            $url = "{$this->endpoint()}/distances/v1/mapbox/driving/{$coordString}";

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
     * Ambil matriks jarak/durasi dari OSRM Table API.
     */
    private function osrmMatrix($coordString)
    {
        $url = $this->endpoint() . '/table/v1/driving/' . $coordString;

        $response = Http::get($url, [
            'annotations' => 'distance,duration',
        ]);

        if ($response->successful()) {
            $data = $response->json();
            if (!is_array($data) || ($data['code'] ?? '') !== 'Ok') {
                Log::error('MapService OSRM Matrix Error: ' . json_encode($data));
                return [
                    'success' => false,
                    'message' => 'Gagal mengambil matriks jarak dari OpenStreetMap: ' . ($data['message'] ?? 'Invalid data.'),
                    'error' => $data,
                ];
            }
            return [
                'success' => true,
                'data' => $data
            ];
        }

        Log::error('MapService OSRM Matrix Error: ' . $response->body());
        return [
            'success' => false,
            'message' => 'Gagal mengambil matriks jarak dari OpenStreetMap.',
            'error' => $response->json()
        ];
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
            // Nominatim (OpenStreetMap geocoding) — open source, tanpa API key.
            if ($this->provider === 'osrm') {
                return $this->nominatimSearch($keyword, $proximity);
            }

            // Mapbox Temporary Geocoding API format
            $url = "{$this->endpoint()}/geocoding/v5/mapbox.places/" . urlencode($keyword) . ".json";

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
     * Cari lokasi/POI via Nominatim (OpenStreetMap).
     * Hasil dikonversi ke bentuk GeoJSON feature agar konsisten dengan
     * struktur Mapbox ({features[].place_name, center, geometry}).
     */
    private function nominatimSearch($keyword, $proximity = null)
    {
        $params = [
            'q' => $keyword,
            'format' => 'jsonv2',
            'limit' => 10,
            'addressdetails' => 1,
            'countrycodes' => 'id',
        ];

        if ($proximity) {
            $parts = array_map('trim', explode(',', (string) $proximity));
            if (count($parts) === 2) {
                $params['lon'] = $parts[0];
                $params['lat'] = $parts[1];
            }
        }

        // Nominatim mewajibkan User-Agent yang jelas (kebijakan penggunaan).
        $response = Http::withHeaders([
            'User-Agent' => 'CityCourierApp/1.0 (https://citycourier.pabm.space)',
        ])->get('https://nominatim.openstreetmap.org/search', $params);

        if ($response->successful()) {
            $items = $response->json();
            if (!is_array($items)) {
                $items = [];
            }
            return [
                'success' => true,
                'data' => [
                    'features' => array_map([$this, 'nominatimToFeature'], $items),
                ],
            ];
        }

        Log::error('MapService Nominatim Search Error: ' . $response->body());
        return [
            'success' => false,
            'message' => 'Gagal mencari lokasi pada OpenStreetMap.',
            'error' => $response->json(),
        ];
    }

    private function nominatimToFeature($item)
    {
        $lat = (float) ($item['lat'] ?? 0);
        $lon = (float) ($item['lon'] ?? 0);
        $placeName = $item['display_name'] ?? $item['name'] ?? '';
        $name = $item['name'] ?? '';

        return [
            'id' => $item['place_id'] ?? null,
            'place_type' => ['place'],
            'place_name' => $placeName,
            'text' => $name !== '' ? $name : $placeName,
            'center' => [$lon, $lat],
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [$lon, $lat],
            ],
            'properties' => [
                'lat' => $lat,
                'lon' => $lon,
                'type' => $item['type'] ?? $item['class'] ?? null,
                'display_name' => $placeName,
            ],
            'address' => $item['address'] ?? null,
        ];
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
