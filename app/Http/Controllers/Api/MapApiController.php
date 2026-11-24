<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MapService;
use App\Services\TomTomTrafficService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MapApiController extends Controller
{
    protected $mapService;
    protected TomTomTrafficService $trafficService;

    public function __construct(MapService $mapService, ?TomTomTrafficService $trafficService = null)
    {
        $this->mapService = $mapService;
        $this->trafficService = $trafficService ?? app(TomTomTrafficService::class);
    }

    /**
     * Get routing path between origin and destination.
     */
    public function routing(Request $request)
    {
        if (! MapService::isEnabled()) {
            return response()->json([
                'success' => false,
                'error_code' => 'PROVIDER_DISABLED',
                'message' => 'Provider Peta & Geocoding sedang dimatikan oleh Admin.',
            ], 503);
        }

        $validator = Validator::make($request->all(), [
            'origin' => 'required|string', // Format: "lng,lat"
            'destination' => 'required|string', // Format: "lng,lat"
            'vias' => 'nullable|array',
            'vias.*' => 'string',
            'travel_mode' => 'nullable|string|in:car,motorcycle',
            'prefer_tomtom' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Format parameter salah.',
                'errors' => $validator->errors()
            ], 422);
        }

        $vias = $request->input('vias', []);
        $preferTomTom = $request->boolean('prefer_tomtom', true);
        if (empty($vias) && $preferTomTom && $this->trafficService->isRoutingReady()) {
            $orig = $this->parseLngLatPair((string) $request->input('origin'));
            $dest = $this->parseLngLatPair((string) $request->input('destination'));
            if ($orig !== null && $dest !== null) {
                $tomtomResult = $this->trafficService->getDirectionsForRoutingProxy(
                    $orig['lat'],
                    $orig['lng'],
                    $dest['lat'],
                    $dest['lng'],
                    (string) $request->input('travel_mode', 'motorcycle')
                );
                if (! empty($tomtomResult['success'])) {
                    return response()->json([
                        'success' => true,
                        'data' => $tomtomResult['data'],
                    ]);
                }
            }
        }

        $result = $this->mapService->getRoute($request->origin, $request->destination, $vias);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'data' => $result['data']
            ]);
        }

        return response()->json([
            'success' => false,
            'error_code' => !empty($result['disabled']) ? 'PROVIDER_DISABLED' : null,
            'message' => $result['message'],
            'error' => $result['error'] ?? null
        ], !empty($result['disabled']) ? 503 : 400);
    }

    /**
     * Parse format koordinat "lng,lat" menjadi ['lat' => float, 'lng' => float].
     *
     * @return array{lat: float, lng: float}|null
     */
    private function parseLngLatPair(string $raw): ?array
    {
        $parts = array_map('trim', explode(',', $raw));
        if (count($parts) !== 2 || ! is_numeric($parts[0]) || ! is_numeric($parts[1])) {
            return null;
        }
        $lng = (float) $parts[0];
        $lat = (float) $parts[1];
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        return ['lat' => $lat, 'lng' => $lng];
    }

    /**
     * Search POI autocomplete locations.
     */
    public function autocomplete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:2',
            'proximity' => 'nullable|string' // Format: "lng,lat"
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter pencarian minimal 2 karakter.',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->mapService->searchPOI($request->q, $request->proximity);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'data' => $result['data']['features'] ?? []
            ]);
        }

        return response()->json([
            'success' => false,
            'error_code' => !empty($result['disabled']) ? 'PROVIDER_DISABLED' : null,
            'message' => $result['message'],
            'error' => $result['error'] ?? null
        ], !empty($result['disabled']) ? 503 : 400);
    }

    /**
     * Get distance and duration matrix.
     */
    public function matrix(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'coordinates' => 'required|array|min:2',
            'coordinates.*' => 'required|string' // Format: "lng,lat"
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Minimal dibutuhkan 2 titik koordinat.',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->mapService->getMatrix($request->coordinates);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'data' => $result['data']
            ]);
        }

        return response()->json([
            'success' => false,
            'error_code' => !empty($result['disabled']) ? 'PROVIDER_DISABLED' : null,
            'message' => $result['message'],
            'error' => $result['error'] ?? null
        ], !empty($result['disabled']) ? 503 : 400);
    }

    /**
     * Get Style JSON and proxy external tile resources securely.
     */
    public function getStyle($name)
    {
        if (!MapService::isEnabled()) {
            return response()->json([
                'success' => false,
                'error_code' => 'PROVIDER_DISABLED',
                'message' => 'Provider Peta & Geocoding sedang dimatikan oleh Admin.',
            ], 503);
        }

        $cacheKey = "map_style_" . $name;

        $styleJson = \Illuminate\Support\Facades\Cache::remember($cacheKey, 86400, function () use ($name) {
            // Pemanggilan service untuk mengambil file style
            $result = $this->mapService->getStyleContent($name);
            if (!$result['success']) {
                return null;
            }

            $data = $result['data'];

            // Mengubah URL sumber Vector Tiles agar diproxy via Laravel
            // Ini menyembunyikan API key penyedia peta dari client
            if (isset($data['sources']['openmaptiles']['tiles'])) {
                $data['sources']['openmaptiles']['tiles'] = [
                    url("/api/shipping/map/tiles/{z}/{x}/{y}.pbf")
                ];
            }
            
            // Proxying assets lainnya jika diset
            $data['sprite'] = url("/api/shipping/map/sprites/sprite");
            $data['glyphs'] = url("/api/shipping/map/fonts/{fontstack}/{range}.pbf");

            return $data;
        });

        if (!$styleJson) {
            return response()->json([
                'success' => false,
                'message' => 'Style peta tidak ditemukan atau gagal diambil.'
            ], 404);
        }

        return response()->json($styleJson);
    }

    /**
     * Proxy TomTom Traffic Flow Segment Data v4 (kecepatan aktual vs bebas hambatan).
     * GET /api/shipping/map/traffic/flow?lat=...&lng=...&zoom=15
     */
    public function trafficFlow(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'zoom' => 'nullable|integer|between:0,22',
            'style' => 'nullable|string|in:'.implode(',', TomTomTrafficService::FLOW_STYLES),
            'include_road_name' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error_code' => 'VALIDATION_ERROR',
                'message' => 'Parameter koordinat lalu lintas tidak valid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->trafficService->getFlowSegment(
            (float) $request->query('lat'),
            (float) $request->query('lng'),
            (int) $request->query('zoom', 15),
            (string) $request->query('style', 'relative0'),
            $request->boolean('include_road_name', true)
        );

        return $this->trafficJsonResponse($result);
    }

    /**
     * Proxy TomTom Traffic Incident Details v5 dalam bounding box peta.
     * GET /api/shipping/map/traffic/incidents?min_lat=...&min_lng=...&max_lat=...&max_lng=...
     */
    public function trafficIncidents(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'min_lat' => 'required|numeric|between:-90,90',
            'min_lng' => 'required|numeric|between:-180,180',
            'max_lat' => 'required|numeric|between:-90,90|gt:min_lat',
            'max_lng' => 'required|numeric|between:-180,180|gt:min_lng',
            'language' => 'nullable|string|max:12',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error_code' => 'VALIDATION_ERROR',
                'message' => 'Parameter bounding box insiden lalu lintas tidak valid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $minLat = (float) $request->query('min_lat');
        $minLng = (float) $request->query('min_lng');
        $maxLat = (float) $request->query('max_lat');
        $maxLng = (float) $request->query('max_lng');

        if (($maxLat - $minLat) > 2.0 || ($maxLng - $minLng) > 2.0) {
            return response()->json([
                'success' => false,
                'error_code' => 'BBOX_TOO_LARGE',
                'message' => 'Area peta terlalu luas. Perbesar zoom untuk memuat data insiden lalu lintas.',
            ], 422);
        }

        $result = $this->trafficService->getIncidents(
            $minLat,
            $minLng,
            $maxLat,
            $maxLng,
            (string) $request->query('language', 'id-ID')
        );

        return $this->trafficJsonResponse($result);
    }

    /**
     * Proxy TomTom Traffic Flow Raster Tile v4 di atas OpenStreetMap.
     * GET /api/shipping/map/traffic/tiles/{z}/{x}/{y}.png
     */
    public function trafficTile(Request $request, int $z, int $x, int $y)
    {
        if ($z < 0 || $z > 22) {
            return response()->json([
                'success' => false,
                'error_code' => 'INVALID_TILE',
                'message' => 'Level zoom tile tidak valid (0–22).',
            ], 422);
        }

        $maxCoord = (1 << $z) - 1;
        if ($x < 0 || $x > $maxCoord || $y < 0 || $y > $maxCoord) {
            return response()->json([
                'success' => false,
                'error_code' => 'INVALID_TILE',
                'message' => 'Koordinat tile berada di luar jangkauan.',
            ], 422);
        }

        $style = (string) $request->query('style', 'relative0');
        $tileSize = (int) $request->query('tile_size', 256);
        $thickness = (int) $request->query('thickness', 4);

        $result = $this->trafficService->getFlowTile($z, $x, $y, $style, $tileSize, $thickness);

        if (! ($result['success'] ?? false)) {
            return $this->trafficJsonResponse($result);
        }

        $ttl = (int) ($result['cache_ttl'] ?? 60);

        return response($result['body'], 200)
            ->header('Content-Type', (string) ($result['content_type'] ?? 'image/png'))
            ->header('Cache-Control', "public, max-age={$ttl}");
    }

    /**
     * Ringkasan lalu lintas di sekitar titik Penjemputan, Tujuan, dan koridor pengiriman kurir.
     * POST /api/shipping/map/traffic/delivery-summary
     */
    public function deliveryTrafficSummary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pickup_lat' => 'required|numeric|between:-90,90',
            'pickup_lng' => 'required|numeric|between:-180,180',
            'dropoff_lat' => 'required|numeric|between:-90,90',
            'dropoff_lng' => 'required|numeric|between:-180,180',
            'pickup_road_hint' => 'nullable|string|max:160',
            'dropoff_road_hint' => 'nullable|string|max:160',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error_code' => 'VALIDATION_ERROR',
                'message' => 'Koordinat penjemputan/tujuan tidak valid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->trafficService->getDeliverySummary(
            (float) $request->input('pickup_lat'),
            (float) $request->input('pickup_lng'),
            (float) $request->input('dropoff_lat'),
            (float) $request->input('dropoff_lng'),
            $request->input('pickup_road_hint') ? (string) $request->input('pickup_road_hint') : null,
            $request->input('dropoff_road_hint') ? (string) $request->input('dropoff_road_hint') : null
        );

        return $this->trafficJsonResponse($result);
    }

    /**
     * Analisa kondisi lalu lintas sepanjang polyline rute kurir menuju penerima.
     * POST /api/shipping/map/traffic/route-analysis
     */
    public function routeTrafficAnalysis(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'coordinates' => 'required|array|min:2|max:1500',
            'coordinates.*.lat' => 'required|numeric|between:-90,90',
            'coordinates.*.lng' => 'required|numeric|between:-180,180',
            'courier_lat' => 'nullable|numeric|between:-90,90',
            'courier_lng' => 'nullable|numeric|between:-180,180',
            'travel_mode' => 'nullable|string|in:car,motorcycle',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error_code' => 'VALIDATION_ERROR',
                'message' => 'Data polyline rute atau koordinat kurir tidak valid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->trafficService->analyzeRouteTraffic(
            (array) $request->input('coordinates', []),
            $request->filled('courier_lat') ? (float) $request->input('courier_lat') : null,
            $request->filled('courier_lng') ? (float) $request->input('courier_lng') : null,
            (string) $request->input('travel_mode', 'car')
        );

        return $this->trafficJsonResponse($result);
    }

    /**
     * Ambil rute utama + alternatif berbasis lalu lintas real-time dari TomTom Routing v1.
     * POST /api/shipping/map/traffic/alternatives
     */
    public function trafficAlternatives(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'origin_lat' => 'required|numeric|between:-90,90',
            'origin_lng' => 'required|numeric|between:-180,180',
            'destination_lat' => 'required|numeric|between:-90,90',
            'destination_lng' => 'required|numeric|between:-180,180',
            'travel_mode' => 'nullable|string|in:car,motorcycle',
            'max_alternatives' => 'nullable|integer|min:1|max:3',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error_code' => 'VALIDATION_ERROR',
                'message' => 'Koordinat asal/tujuan tidak valid untuk pencarian rute alternatif.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->trafficService->getTrafficAwareRoutes(
            (float) $request->input('origin_lat'),
            (float) $request->input('origin_lng'),
            (float) $request->input('destination_lat'),
            (float) $request->input('destination_lng'),
            (string) $request->input('travel_mode', 'motorcycle'),
            (int) $request->input('max_alternatives', 2)
        );

        return $this->trafficJsonResponse($result);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function trafficJsonResponse(array $result)
    {
        if (! empty($result['success'])) {
            return response()->json([
                'success' => true,
                'data' => $result['data'],
            ], (int) ($result['status_code'] ?? 200));
        }

        return response()->json([
            'success' => false,
            'error_code' => $result['error_code'] ?? 'TRAFFIC_ERROR',
            'message' => $result['message'] ?? 'Gagal mengambil data lalu lintas.',
        ], (int) ($result['status_code'] ?? 502));
    }
}
