<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Proxy & klasifikasi TomTom Traffic API (Flow Segment v4, Flow Tiles v4, Incidents v5).
 *
 * API Key hanya dibaca di backend (`config('services.tomtom.api_key')` / `.env`) dan
 * tidak pernah dikirim ke client maupun dicatat ke log aplikasi.
 */
class TomTomTrafficService
{
    public const FLOW_STYLES = ['relative0', 'relative', 'absolute', 'relative-delay', 'reduced-sensitivity'];

    /**
     * Klasifikasi kemacetan resmi City Courier:
     * - Rasio kecepatan = (currentSpeed / freeFlowSpeed) * 100%
     * - >= 70%  : Lancar (hijau)
     * - 50-69%  : Mulai padat (kuning)
     * - 30-49%  : Padat (oranye)
     * - < 30%   : Macet (merah)
     * Nilai null / tidak valid / negatif / freeFlowSpeed <= 0 TIDAK pernah dianggap lancar.
     *
     * @return array<string, mixed>
     */
    public static function classifyCongestion(
        int|float|null $currentSpeed,
        int|float|null $freeFlowSpeed,
        bool $roadClosure = false,
        ?float $confidence = null
    ): array {
        if ($roadClosure) {
            return [
                'status' => 'road_closed',
                'label' => 'Jalan Ditutup',
                'color' => 'red',
                'color_hex' => '#DC2626',
                'speed_ratio_percent' => 0.0,
                'congestion_percent' => 100.0,
                'is_available' => true,
                'is_congested' => true,
            ];
        }

        if (
            $currentSpeed === null
            || $freeFlowSpeed === null
            || ! is_numeric($currentSpeed)
            || ! is_numeric($freeFlowSpeed)
            || (float) $currentSpeed < 0
            || (float) $freeFlowSpeed <= 0
        ) {
            return [
                'status' => 'unknown',
                'label' => 'Data Tidak Tersedia',
                'color' => 'gray',
                'color_hex' => '#64748B',
                'speed_ratio_percent' => null,
                'congestion_percent' => null,
                'is_available' => false,
                'is_congested' => false,
            ];
        }

        $cur = (float) $currentSpeed;
        $free = (float) $freeFlowSpeed;
        $rawRatio = ($cur / $free) * 100.0;
        $ratio = round(min(100.0, max(0.0, $rawRatio)), 1);
        $congestionPercent = round(max(0.0, min(100.0, 100.0 - $ratio)), 1);

        if ($rawRatio >= 70.0) {
            return [
                'status' => 'smooth',
                'label' => 'Lancar',
                'color' => 'green',
                'color_hex' => '#16A34A',
                'speed_ratio_percent' => $ratio,
                'congestion_percent' => $congestionPercent,
                'is_available' => true,
                'is_congested' => false,
            ];
        }

        if ($rawRatio >= 50.0) {
            return [
                'status' => 'moderate',
                'label' => 'Mulai padat',
                'color' => 'yellow',
                'color_hex' => '#EAB308',
                'speed_ratio_percent' => $ratio,
                'congestion_percent' => $congestionPercent,
                'is_available' => true,
                'is_congested' => false,
            ];
        }

        if ($rawRatio >= 30.0) {
            return [
                'status' => 'heavy',
                'label' => 'Padat',
                'color' => 'orange',
                'color_hex' => '#F97316',
                'speed_ratio_percent' => $ratio,
                'congestion_percent' => $congestionPercent,
                'is_available' => true,
                'is_congested' => true,
            ];
        }

        return [
            'status' => 'congested',
            'label' => 'Macet',
            'color' => 'red',
            'color_hex' => '#DC2626',
            'speed_ratio_percent' => $ratio,
            'congestion_percent' => $congestionPercent,
            'is_available' => true,
            'is_congested' => true,
        ];
    }

    public function isConfigured(): bool
    {
        return $this->apiKey() !== '';
    }

    /**
     * Ambil Flow Segment Data v4 untuk koordinat jalan tertentu.
     *
     * @return array<string, mixed>
     */
    public function getFlowSegment(
        float $lat,
        float $lng,
        int $zoom = 15,
        string $style = 'relative0',
        bool $includeRoadName = true
    ): array {
        if (! MapService::isEnabled()) {
            return $this->disabledPayload();
        }

        $apiKey = $this->apiKey();
        if ($apiKey === '') {
            return $this->unconfiguredPayload();
        }

        $zoom = max(0, min(22, $zoom));
        $style = in_array($style, self::FLOW_STYLES, true) ? $style : 'relative0';
        $ttl = max(15, (int) config('services.tomtom.flow_cache_ttl_seconds', 60));

        $latKey = number_format($lat, 4, '.', '');
        $lngKey = number_format($lng, 4, '.', '');
        $cacheKey = "tomtom:flow:{$style}:{$zoom}:{$latKey}:{$lngKey}";

        $cached = $this->readFreshCache($cacheKey);
        if ($cached !== null) {
            if ($includeRoadName && empty($cached['road_name'])) {
                $cached['road_name'] = $this->resolveRoadName($lat, $lng);
            }
            return $this->finalizeSuccessResponse($cached, 'tomtom_cache', $ttl);
        }

        try {
            $url = "{$this->baseUrl()}/traffic/services/4/flowSegmentData/{$style}/{$zoom}/json";
            $response = Http::timeout($this->timeoutSeconds())
                ->acceptJson()
                ->get($url, [
                    'key' => $apiKey,
                    'point' => "{$lat},{$lng}",
                    'unit' => 'KMPH',
                    'openLr' => 'false',
                ]);

            if (! $response->successful()) {
                Log::warning('TomTom Flow Segment API failed', [
                    'status' => $response->status(),
                ]);

                return $this->errorPayload(
                    'TOMTOM_UPSTREAM_ERROR',
                    'Gagal mengambil data kecepatan lalu lintas dari TomTom (HTTP '.$response->status().').',
                    $response->status() === 429 ? 429 : 502
                );
            }

            $json = $response->json();
            $flow = is_array($json) ? ($json['flowSegmentData'] ?? null) : null;
            if (! is_array($flow)) {
                return $this->errorPayload(
                    'TOMTOM_INVALID_RESPONSE',
                    'Format respons TomTom Traffic Flow tidak valid.',
                    502
                );
            }

            $currentSpeed = is_numeric($flow['currentSpeed'] ?? null) ? (int) round((float) $flow['currentSpeed']) : null;
            $freeFlowSpeed = is_numeric($flow['freeFlowSpeed'] ?? null) ? (int) round((float) $flow['freeFlowSpeed']) : null;
            $roadClosure = filter_var($flow['roadClosure'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $confidence = is_numeric($flow['confidence'] ?? null) ? (float) $flow['confidence'] : null;

            $classification = self::classifyCongestion($currentSpeed, $freeFlowSpeed, $roadClosure, $confidence);

            $rawCoords = $flow['coordinates']['coordinate'] ?? [];
            $segmentCoords = [];
            if (is_array($rawCoords)) {
                foreach ($rawCoords as $pt) {
                    if (is_array($pt) && is_numeric($pt['latitude'] ?? null) && is_numeric($pt['longitude'] ?? null)) {
                        $segmentCoords[] = [
                            'lat' => (float) $pt['latitude'],
                            'lng' => (float) $pt['longitude'],
                        ];
                    }
                }
            }

            $now = CarbonImmutable::now('UTC');
            $data = [
                'point' => ['lat' => $lat, 'lng' => $lng],
                'road_name' => $includeRoadName ? $this->resolveRoadName($lat, $lng) : null,
                'frc' => $flow['frc'] ?? null,
                'current_speed_kmh' => $currentSpeed,
                'free_flow_speed_kmh' => $freeFlowSpeed,
                'current_travel_time_seconds' => is_numeric($flow['currentTravelTime'] ?? null) ? (int) $flow['currentTravelTime'] : null,
                'free_flow_travel_time_seconds' => is_numeric($flow['freeFlowTravelTime'] ?? null) ? (int) $flow['freeFlowTravelTime'] : null,
                'confidence' => $confidence,
                'road_closure' => $roadClosure,
                'status' => $classification['status'],
                'status_label' => $classification['label'],
                'color' => $classification['color'],
                'color_hex' => $classification['color_hex'],
                'speed_ratio_percent' => $classification['speed_ratio_percent'],
                'congestion_percent' => $classification['congestion_percent'],
                'is_available' => $classification['is_available'],
                'is_congested' => $classification['is_congested'],
                'coordinates' => $segmentCoords,
                'fetched_at' => $now->toIso8601String(),
                'expires_at' => $now->addSeconds($ttl)->toIso8601String(),
            ];

            Cache::put($cacheKey, $data, $now->addSeconds($ttl));

            return $this->finalizeSuccessResponse($data, 'tomtom_live', $ttl);
        } catch (\Throwable $e) {
            Log::warning('TomTom Flow Segment exception', [
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
            ]);

            return $this->errorPayload(
                'TOMTOM_CONNECTION_ERROR',
                'Koneksi ke layanan lalu lintas TomTom gagal atau timeout.',
                504
            );
        }
    }

    /**
     * Ambil Traffic Incidents Details v5 dalam bounding box peta.
     *
     * @return array<string, mixed>
     */
    public function getIncidents(
        float $minLat,
        float $minLng,
        float $maxLat,
        float $maxLng,
        string $language = 'id-ID'
    ): array {
        if (! MapService::isEnabled()) {
            return $this->disabledPayload();
        }

        $apiKey = $this->apiKey();
        if ($apiKey === '') {
            return $this->unconfiguredPayload();
        }

        $ttl = max(15, (int) config('services.tomtom.incidents_cache_ttl_seconds', 60));
        $grid = static fn (float $v): string => number_format(round($v / 0.005) * 0.005, 3, '.', '');
        $bboxKey = implode(',', [$grid($minLng), $grid($minLat), $grid($maxLng), $grid($maxLat)]);
        $lang = trim($language) !== '' ? trim($language) : 'id-ID';
        $cacheKey = "tomtom:incidents:{$lang}:{$bboxKey}";

        $cached = $this->readFreshCache($cacheKey);
        if ($cached !== null) {
            return $this->finalizeSuccessResponse($cached, 'tomtom_cache', $ttl);
        }

        try {
            $url = "{$this->baseUrl()}/traffic/services/5/incidentDetails";
            $fields = '{incidents{type,geometry{type,coordinates},properties{id,iconCategory,magnitudeOfDelay,events{description,code,iconCategory},startTime,endTime,from,to,length,delay,roadNumbers,timeValidity}}}';
            $response = Http::timeout($this->timeoutSeconds())
                ->acceptJson()
                ->get($url, [
                    'key' => $apiKey,
                    'bbox' => "{$minLng},{$minLat},{$maxLng},{$maxLat}",
                    'fields' => $fields,
                    'language' => $lang,
                    'timeValidityFilter' => 'present',
                ]);

            if (! $response->successful()) {
                Log::warning('TomTom Incidents API failed', [
                    'status' => $response->status(),
                ]);

                return $this->errorPayload(
                    'TOMTOM_UPSTREAM_ERROR',
                    'Gagal mengambil data insiden lalu lintas dari TomTom (HTTP '.$response->status().').',
                    $response->status() === 429 ? 429 : 502
                );
            }

            $json = $response->json();
            if (! is_array($json) || ! array_key_exists('incidents', $json) || ! is_array($json['incidents'])) {
                return $this->errorPayload(
                    'TOMTOM_INVALID_RESPONSE',
                    'Format respons TomTom Traffic Incidents tidak valid.',
                    502
                );
            }

            $incidents = [];
            foreach ($json['incidents'] as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $normalized = $this->normalizeIncident($item);
                if ($normalized !== null) {
                    $incidents[] = $normalized;
                }
            }

            $now = CarbonImmutable::now('UTC');
            $data = [
                'bbox' => [
                    'min_lat' => $minLat,
                    'min_lng' => $minLng,
                    'max_lat' => $maxLat,
                    'max_lng' => $maxLng,
                ],
                'count' => count($incidents),
                'incidents' => $incidents,
                'fetched_at' => $now->toIso8601String(),
                'expires_at' => $now->addSeconds($ttl)->toIso8601String(),
            ];

            Cache::put($cacheKey, $data, $now->addSeconds($ttl));

            return $this->finalizeSuccessResponse($data, 'tomtom_live', $ttl);
        } catch (\Throwable $e) {
            Log::warning('TomTom Incidents exception', [
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
            ]);

            return $this->errorPayload(
                'TOMTOM_CONNECTION_ERROR',
                'Koneksi ke layanan insiden TomTom gagal atau timeout.',
                504
            );
        }
    }

    /**
     * Proxy binary PNG Raster Flow Tile v4 agar TOMTOM_API_KEY tetap tersembunyi di server.
     *
     * @return array{success: bool, body?: string, content_type?: string, cache_ttl?: int, error_code?: string, message?: string, status_code?: int}
     */
    public function getFlowTile(
        int $z,
        int $x,
        int $y,
        string $style = 'relative0',
        int $tileSize = 256,
        int $thickness = 4
    ): array {
        if (! MapService::isEnabled()) {
            return $this->disabledPayload();
        }

        $apiKey = $this->apiKey();
        if ($apiKey === '') {
            return $this->unconfiguredPayload();
        }

        $style = in_array($style, self::FLOW_STYLES, true) ? $style : 'relative0';
        $tileSize = $tileSize === 512 ? 512 : 256;
        $thickness = max(1, min(20, $thickness));
        $ttl = max(15, (int) config('services.tomtom.tile_cache_ttl_seconds', 60));

        $cacheKey = "tomtom:tile:{$style}:{$tileSize}:{$thickness}:{$z}:{$x}:{$y}";
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && ! empty($cached['b64']) && ! empty($cached['expires_at'])) {
            $expiresAt = CarbonImmutable::parse((string) $cached['expires_at']);
            if (CarbonImmutable::now('UTC')->lessThanOrEqualTo($expiresAt)) {
                $decoded = base64_decode((string) $cached['b64'], true);
                if ($decoded !== false) {
                    return [
                        'success' => true,
                        'body' => $decoded,
                        'content_type' => (string) ($cached['content_type'] ?? 'image/png'),
                        'cache_ttl' => max(1, CarbonImmutable::now('UTC')->diffInSeconds($expiresAt)),
                    ];
                }
            }
            Cache::forget($cacheKey);
        }

        try {
            $url = "{$this->baseUrl()}/traffic/map/4/tile/flow/{$style}/{$z}/{$x}/{$y}.png";
            $response = Http::timeout($this->timeoutSeconds())
                ->get($url, [
                    'key' => $apiKey,
                    'tileSize' => $tileSize,
                    'thickness' => $thickness,
                ]);

            if (! $response->successful()) {
                return $this->errorPayload(
                    'TOMTOM_TILE_ERROR',
                    'Gagal memuat tile lalu lintas TomTom (HTTP '.$response->status().').',
                    $response->status() === 429 ? 429 : 502
                );
            }

            $bytes = $response->body();
            if ($bytes === '') {
                return $this->errorPayload('TOMTOM_EMPTY_TILE', 'Tile lalu lintas kosong.', 502);
            }

            $contentType = $response->header('Content-Type') ?: 'image/png';
            $now = CarbonImmutable::now('UTC');
            Cache::put($cacheKey, [
                'b64' => base64_encode($bytes),
                'content_type' => $contentType,
                'expires_at' => $now->addSeconds($ttl)->toIso8601String(),
            ], $now->addSeconds($ttl));

            return [
                'success' => true,
                'body' => $bytes,
                'content_type' => $contentType,
                'cache_ttl' => $ttl,
            ];
        } catch (\Throwable $e) {
            Log::warning('TomTom Flow Tile exception', [
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
            ]);

            return $this->errorPayload(
                'TOMTOM_CONNECTION_ERROR',
                'Koneksi ke tile lalu lintas TomTom gagal atau timeout.',
                504
            );
        }
    }

    /**
     * Ringkasan lalu lintas khusus untuk halaman Detail Pengiriman Kurir:
     * memeriksa kondisi sekitar Penjemputan, sekitar Tujuan, dan insiden di koridornya.
     *
     * @return array<string, mixed>
     */
    public function getDeliverySummary(
        float $pickupLat,
        float $pickupLng,
        float $dropoffLat,
        float $dropoffLng,
        ?string $pickupRoadHint = null,
        ?string $dropoffRoadHint = null
    ): array {
        if (! MapService::isEnabled()) {
            return $this->disabledPayload();
        }

        if (! $this->isConfigured()) {
            return $this->unconfiguredPayload();
        }

        $pickupFlow = $this->getFlowSegment($pickupLat, $pickupLng, 15, 'relative0', false);
        $dropoffFlow = $this->getFlowSegment($dropoffLat, $dropoffLng, 15, 'relative0', false);

        if (! $pickupFlow['success'] && ! $dropoffFlow['success']) {
            return $pickupFlow;
        }

        $minLat = max(-89.9, min($pickupLat, $dropoffLat) - 0.015);
        $maxLat = min(89.9, max($pickupLat, $dropoffLat) + 0.015);
        $minLng = max(-179.9, min($pickupLng, $dropoffLng) - 0.015);
        $maxLng = min(179.9, max($pickupLng, $dropoffLng) + 0.015);

        // Pastikan lebar/tinggi bbox tidak melebihi batas aman API
        if (($maxLat - $minLat) > 1.8) {
            $midLat = ($pickupLat + $dropoffLat) / 2;
            $minLat = $midLat - 0.9;
            $maxLat = $midLat + 0.9;
        }
        if (($maxLng - $minLng) > 1.8) {
            $midLng = ($pickupLng + $dropoffLng) / 2;
            $minLng = $midLng - 0.9;
            $maxLng = $midLng + 0.9;
        }

        $incidentsRes = $this->getIncidents($minLat, $minLng, $maxLat, $maxLng);
        $incidents = ($incidentsRes['success'] ?? false) ? ($incidentsRes['data']['incidents'] ?? []) : [];

        $pickupData = $pickupFlow['success'] ? $pickupFlow['data'] : null;
        if ($pickupData !== null && empty($pickupData['road_name']) && $pickupRoadHint) {
            $pickupData['road_name'] = $pickupRoadHint;
        }

        $dropoffData = $dropoffFlow['success'] ? $dropoffFlow['data'] : null;
        if ($dropoffData !== null && empty($dropoffData['road_name']) && $dropoffRoadHint) {
            $dropoffData['road_name'] = $dropoffRoadHint;
        }

        $warnings = [];
        if ($pickupData && ($pickupData['is_congested'] ?? false)) {
            $warnings[] = [
                'area' => 'pickup',
                'severity' => $pickupData['status'],
                'title' => 'Lalu lintas di sekitar lokasi penjemputan '.strtolower((string) $pickupData['status_label']),
                'detail' => $this->formatSpeedAlertDetail($pickupData),
            ];
        }
        if ($dropoffData && ($dropoffData['is_congested'] ?? false)) {
            $warnings[] = [
                'area' => 'dropoff',
                'severity' => $dropoffData['status'],
                'title' => 'Lalu lintas di sekitar lokasi tujuan '.strtolower((string) $dropoffData['status_label']),
                'detail' => $this->formatSpeedAlertDetail($dropoffData),
            ];
        }

        foreach ($incidents as $inc) {
            if (! is_array($inc)) {
                continue;
            }
            $category = (string) ($inc['category'] ?? '');
            $mag = (string) ($inc['severity'] ?? '');
            if (in_array($category, ['accident', 'road_closed', 'lane_closed', 'road_works', 'flooding', 'jam'], true)
                || in_array($mag, ['moderate', 'major', 'closure'], true)) {
                $road = trim((string) ($inc['from'] ?? $inc['road_label'] ?? ''));
                $warnings[] = [
                    'area' => 'corridor',
                    'severity' => $category === 'road_closed' ? 'road_closed' : ($mag ?: 'moderate'),
                    'title' => (string) ($inc['category_label'] ?? 'Gangguan Lalu Lintas').($road !== '' ? " di {$road}" : ''),
                    'detail' => (string) ($inc['description'] ?? 'Pantau kondisi jalan saat menuju lokasi.'),
                ];
            }
            if (count($warnings) >= 5) {
                break;
            }
        }

        $now = CarbonImmutable::now('UTC');
        $ttl = max(15, (int) config('services.tomtom.flow_cache_ttl_seconds', 60));

        return [
            'success' => true,
            'status_code' => 200,
            'data' => [
                'pickup_traffic' => $pickupData,
                'dropoff_traffic' => $dropoffData,
                'incidents_count' => count($incidents),
                'incidents' => array_slice($incidents, 0, 15),
                'has_congestion' => ! empty($warnings),
                'congestion_warnings' => $warnings,
                'alternative_route' => [
                    'supported' => false,
                    'routing_engine' => 'osrm_openstreetmap',
                    'traffic_aware' => false,
                    'recommendation_available' => false,
                    'message' => 'Rekomendasi rute alternatif berbasis lalu lintas belum aktif karena routing saat ini masih menggunakan OSRM OpenStreetMap tanpa pembobotan kemacetan real-time.',
                ],
                'fetched_at' => $now->toIso8601String(),
                'expires_at' => $now->addSeconds($ttl)->toIso8601String(),
                'ttl_seconds' => $ttl,
                'age_seconds' => 0,
                'is_stale' => false,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function normalizeIncident(array $item): ?array
    {
        $geometry = is_array($item['geometry'] ?? null) ? $item['geometry'] : [];
        $props = is_array($item['properties'] ?? null) ? $item['properties'] : [];
        $coords = $this->extractCoordinates($geometry);
        if (empty($coords)) {
            return null;
        }

        $rawCategory = $props['iconCategory'] ?? ($props['events'][0]['iconCategory'] ?? 0);
        [$categoryKey, $categoryLabel] = $this->mapIncidentCategory($rawCategory);

        $rawMagnitude = $props['magnitudeOfDelay'] ?? 0;
        [$severityKey, $severityLabel] = $this->mapMagnitude($rawMagnitude, $categoryKey);

        $events = is_array($props['events'] ?? null) ? $props['events'] : [];
        $descriptions = [];
        foreach ($events as $ev) {
            if (is_array($ev) && ! empty($ev['description'])) {
                $descriptions[] = trim((string) $ev['description']);
            }
        }
        $description = ! empty($descriptions) ? implode(' • ', array_unique($descriptions)) : $categoryLabel;

        $roadNumbers = is_array($props['roadNumbers'] ?? null)
            ? array_values(array_filter(array_map('strval', $props['roadNumbers'])))
            : [];

        $from = isset($props['from']) ? trim((string) $props['from']) : null;
        $to = isset($props['to']) ? trim((string) $props['to']) : null;

        $roadLabel = '';
        if (! empty($roadNumbers)) {
            $roadLabel = implode(', ', $roadNumbers);
        }
        if ($from) {
            $roadLabel = $roadLabel !== '' ? "{$roadLabel} ({$from})" : $from;
        }

        $centerPoint = $coords[(int) floor(count($coords) / 2)];

        return [
            'id' => (string) ($props['id'] ?? md5(json_encode($centerPoint).$description)),
            'category' => $categoryKey,
            'category_label' => $categoryLabel,
            'icon_category' => $rawCategory,
            'severity' => $severityKey,
            'severity_label' => $severityLabel,
            'magnitude_of_delay' => $rawMagnitude,
            'description' => $description,
            'from' => $from !== '' ? $from : null,
            'to' => $to !== '' ? $to : null,
            'road_numbers' => $roadNumbers,
            'road_label' => $roadLabel !== '' ? $roadLabel : null,
            'delay_seconds' => is_numeric($props['delay'] ?? null) ? (int) $props['delay'] : null,
            'length_meters' => is_numeric($props['length'] ?? null) ? (int) $props['length'] : null,
            'start_time' => $props['startTime'] ?? null,
            'end_time' => $props['endTime'] ?? null,
            'point' => $centerPoint,
            'coordinates' => $coords,
        ];
    }

    /**
     * @param  array<string, mixed>  $geometry
     * @return array<int, array{lat: float, lng: float}>
     */
    private function extractCoordinates(array $geometry): array
    {
        $type = strtolower((string) ($geometry['type'] ?? ''));
        $raw = $geometry['coordinates'] ?? null;
        if (! is_array($raw)) {
            return [];
        }

        $points = [];
        if ($type === 'point' && count($raw) >= 2 && is_numeric($raw[0]) && is_numeric($raw[1])) {
            $points[] = ['lat' => (float) $raw[1], 'lng' => (float) $raw[0]];
        } elseif ($type === 'linestring' || $type === 'multipoint') {
            foreach ($raw as $pair) {
                if (is_array($pair) && count($pair) >= 2 && is_numeric($pair[0]) && is_numeric($pair[1])) {
                    $points[] = ['lat' => (float) $pair[1], 'lng' => (float) $pair[0]];
                }
            }
        } elseif ($type === 'multilinestring') {
            foreach ($raw as $line) {
                if (! is_array($line)) {
                    continue;
                }
                foreach ($line as $pair) {
                    if (is_array($pair) && count($pair) >= 2 && is_numeric($pair[0]) && is_numeric($pair[1])) {
                        $points[] = ['lat' => (float) $pair[1], 'lng' => (float) $pair[0]];
                    }
                }
            }
        }

        return $points;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function mapIncidentCategory(mixed $raw): array
    {
        $norm = is_numeric($raw) ? (int) $raw : strtolower(trim((string) $raw));

        return match ($norm) {
            1, 'accident' => ['accident', 'Kecelakaan'],
            2, 'fog' => ['fog', 'Kabut Tebal'],
            3, 'dangerous-conditions', 'dangerousconditions' => ['dangerous_conditions', 'Kondisi Jalan Berbahaya'],
            4, 'rain' => ['rain', 'Hujan Lebat'],
            5, 'ice' => ['ice', 'Jalan Licin'],
            6, 'jam' => ['jam', 'Kemacetan'],
            7, 'lane-closed', 'laneclosed' => ['lane_closed', 'Lajur Ditutup'],
            8, 'road-closed', 'roadclosed' => ['road_closed', 'Jalan Ditutup'],
            9, 'roadworks', 'road-works', 'road works' => ['road_works', 'Perbaikan Jalan'],
            10, 'wind' => ['wind', 'Angin Kencang'],
            11, 'flooding' => ['flooding', 'Banjir / Genangan'],
            14, 'broken-down-vehicle', 'brokendownvehicle' => ['broken_down_vehicle', 'Kendaraan Mogok'],
            default => ['other', 'Gangguan Lalu Lintas'],
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function mapMagnitude(mixed $raw, string $categoryKey): array
    {
        if ($categoryKey === 'road_closed') {
            return ['closure', 'Penutupan Jalan'];
        }

        $norm = is_numeric($raw) ? (int) $raw : strtolower(trim((string) $raw));

        return match ($norm) {
            1, 'minor' => ['minor', 'Kepadatan Ringan'],
            2, 'moderate' => ['moderate', 'Kepadatan Sedang'],
            3, 'major' => ['major', 'Kemacetan Parah'],
            4, 'undefined', 'indefinite' => ['closure', 'Gangguan / Penutupan'],
            default => ['unknown', 'Perlu Diwaspadai'],
        };
    }

    private function formatSpeedAlertDetail(array $flow): string
    {
        $cur = $flow['current_speed_kmh'] ?? null;
        $free = $flow['free_flow_speed_kmh'] ?? null;
        $pct = $flow['congestion_percent'] ?? null;
        if (($flow['road_closure'] ?? false) === true) {
            return 'Terdeteksi penutupan jalan di area ini.';
        }
        if ($cur !== null && $free !== null) {
            $pctStr = $pct !== null ? " • Kepadatan {$pct}%" : '';

            return "Kecepatan rata-rata {$cur} km/jam (normal {$free} km/jam{$pctStr}).";
        }

        return 'Pantau arus kendaraan sebelum berangkat.';
    }

    /**
     * Reverse geocode ringan untuk menampilkan nama jalan saat kurir mengetuk ruas jalan di peta.
     */
    private function resolveRoadName(float $lat, float $lng): ?string
    {
        $lat4 = number_format($lat, 4, '.', '');
        $lng4 = number_format($lng, 4, '.', '');
        $cacheKey = "nominatim:road_name:{$lat4}:{$lng4}";

        $cached = Cache::get($cacheKey);
        if (is_string($cached)) {
            return $cached !== '' ? $cached : null;
        }

        try {
            $res = Http::timeout(2)
                ->withHeaders(['User-Agent' => 'CityCourierBackend/1.0'])
                ->acceptJson()
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'format' => 'jsonv2',
                    'lat' => $lat,
                    'lon' => $lng,
                    'zoom' => 17,
                    'addressdetails' => 1,
                ]);

            if ($res->successful()) {
                $json = $res->json();
                $road = trim((string) (
                    $json['address']['road']
                    ?? $json['address']['pedestrian']
                    ?? $json['address']['neighbourhood']
                    ?? $json['name']
                    ?? ''
                ));
                Cache::put($cacheKey, $road, now()->addHours(6));

                return $road !== '' ? $road : null;
            }
        } catch (\Throwable) {
            // Jangan gagalkan respons lalu lintas jika reverse geocoding tidak merespons
        }

        return null;
    }

    /**
     * Baca cache hanya jika belum melewati `expires_at` agar data kedaluwarsa tidak pernah
     * disajikan seolah-olah masih real-time.
     *
     * @return array<string, mixed>|null
     */
    private function readFreshCache(string $cacheKey): ?array
    {
        $cached = Cache::get($cacheKey);
        if (! is_array($cached) || empty($cached['expires_at']) || empty($cached['fetched_at'])) {
            return null;
        }

        try {
            $expiresAt = CarbonImmutable::parse((string) $cached['expires_at']);
            if (CarbonImmutable::now('UTC')->greaterThan($expiresAt)) {
                Cache::forget($cacheKey);

                return null;
            }

            return $cached;
        } catch (\Throwable) {
            Cache::forget($cacheKey);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function finalizeSuccessResponse(array $data, string $source, int $ttl): array
    {
        $now = CarbonImmutable::now('UTC');
        $fetchedAt = CarbonImmutable::parse((string) ($data['fetched_at'] ?? $now->toIso8601String()));
        $expiresAt = CarbonImmutable::parse((string) ($data['expires_at'] ?? $now->addSeconds($ttl)->toIso8601String()));

        $data['source'] = $source;
        $data['ttl_seconds'] = $ttl;
        $data['age_seconds'] = max(0, (int) $fetchedAt->diffInSeconds($now));
        $data['is_stale'] = $now->greaterThan($expiresAt);

        return [
            'success' => true,
            'status_code' => 200,
            'data' => $data,
        ];
    }

    private function apiKey(): string
    {
        return trim((string) config('services.tomtom.api_key', ''));
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.tomtom.base_url', 'https://api.tomtom.com'), '/');
    }

    private function timeoutSeconds(): int
    {
        return max(3, min(20, (int) config('services.tomtom.timeout_seconds', 10)));
    }

    private function sanitizeErrorMessage(string $message): string
    {
        $apiKey = $this->apiKey();
        if ($apiKey !== '') {
            return str_replace($apiKey, '[REDACTED]', $message);
        }

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    private function disabledPayload(): array
    {
        return [
            'success' => false,
            'disabled' => true,
            'error_code' => 'PROVIDER_DISABLED',
            'status_code' => 503,
            'message' => 'Provider Peta & Lalu Lintas sedang dinonaktifkan oleh Admin.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function unconfiguredPayload(): array
    {
        return [
            'success' => false,
            'error_code' => 'TOMTOM_NOT_CONFIGURED',
            'status_code' => 503,
            'message' => 'Layanan lalu lintas TomTom belum dikonfigurasi pada backend (.env TOMTOM_API_KEY).',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function errorPayload(string $code, string $message, int $statusCode): array
    {
        return [
            'success' => false,
            'error_code' => $code,
            'status_code' => $statusCode,
            'message' => $message,
        ];
    }
}
