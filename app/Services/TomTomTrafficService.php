<?php

namespace App\Services;

use App\Modules\Payment\Services\GatewayCredentialsResolver;
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
     * Uji konektivitas TomTom Traffic Flow API dengan kredensial tersimpan atau input form sementara.
     *
     * @return array<string, mixed>
     */
    public function testConnection(
        ?string $overrideApiKey = null,
        ?string $overrideBaseUrl = null,
        ?int $overrideTimeoutSeconds = null
    ): array {
        $creds = $this->credentials();
        $apiKey = trim((string) ($overrideApiKey !== null && trim($overrideApiKey) !== ''
            ? $overrideApiKey
            : ($creds['api_key'] ?? '')));
        $baseUrl = rtrim((string) ($overrideBaseUrl !== null && trim($overrideBaseUrl) !== ''
            ? $overrideBaseUrl
            : ($creds['base_url'] ?? 'https://api.tomtom.com')), '/');
        $timeout = max(3, min(20, (int) ($overrideTimeoutSeconds ?: ($creds['timeout_seconds'] ?? 10))));

        if ($apiKey === '') {
            return $this->errorPayload(
                'TOMTOM_NOT_CONFIGURED',
                'TomTom API Key belum diisi.',
                422
            );
        }

        try {
            $url = "{$baseUrl}/traffic/services/4/flowSegmentData/relative0/15/json";
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->get($url, [
                    'key' => $apiKey,
                    'point' => '-7.2575,112.7521',
                    'unit' => 'KMPH',
                    'openLr' => 'false',
                ]);

            if ($response->successful()) {
                $flow = $response->json('flowSegmentData');
                if (is_array($flow)) {
                    $cur = $flow['currentSpeed'] ?? '-';
                    $free = $flow['freeFlowSpeed'] ?? '-';

                    return [
                        'success' => true,
                        'status_code' => 200,
                        'message' => "Koneksi TomTom Traffic API Berhasil! Sampel kecepatan Surabaya: {$cur}/{$free} km/jam.",
                    ];
                }

                return $this->errorPayload(
                    'TOMTOM_INVALID_RESPONSE',
                    'Server TomTom merespons, namun payload flowSegmentData tidak ditemukan.',
                    502
                );
            }

            $detail = trim((string) ($response->json('detailedError.message')
                ?? $response->json('errorText')
                ?? ''));
            $detail = $detail !== '' ? ': '.$this->sanitizeSecretString($detail, $apiKey) : '. Periksa kembali API Key atau kuota TomTom.';

            return $this->errorPayload(
                'TOMTOM_UPSTREAM_ERROR',
                'TomTom merespons HTTP '.$response->status().$detail,
                $response->status() === 401 || $response->status() === 403 ? $response->status() : 400
            );
        } catch (\Throwable $e) {
            return $this->errorPayload(
                'TOMTOM_CONNECTION_ERROR',
                'Gagal menghubungi TomTom Traffic API: '.$this->sanitizeSecretString($e->getMessage(), $apiKey),
                504
            );
        }
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
        if (! $this->isProviderEnabled()) {
            return $this->disabledPayload();
        }

        $apiKey = $this->apiKey();
        if ($apiKey === '') {
            return $this->unconfiguredPayload();
        }

        $zoom = max(0, min(22, $zoom));
        $style = in_array($style, self::FLOW_STYLES, true) ? $style : 'relative0';
        $ttl = $this->ttlSeconds('flow_cache_ttl_seconds');
        $cacheKey = $this->flowSegmentCacheKey($lat, $lng, $zoom, $style);

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
            $data = is_array($json)
                ? $this->buildFlowSegmentData($json, $lat, $lng, $ttl, $includeRoadName)
                : null;
            if ($data === null) {
                return $this->errorPayload(
                    'TOMTOM_INVALID_RESPONSE',
                    'Format respons TomTom Traffic Flow tidak valid.',
                    502
                );
            }

            Cache::put($cacheKey, $data, CarbonImmutable::now('UTC')->addSeconds($ttl));

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
     * Analisa kondisi lalu lintas SEPANJANG polyline rute pengiriman kurir yang aktif.
     *
     * Hanya memeriksa ruas jalan di sepanjang jalur menuju penerima dan menyaring
     * insiden agar hanya gangguan pada koridor rute (<= 120 meter) yang dimunculkan.
     *
     * @param  array<int, array<string, mixed>>  $rawCoordinates
     * @return array<string, mixed>
     */
    public function analyzeRouteTraffic(
        array $rawCoordinates,
        ?float $courierLat = null,
        ?float $courierLng = null,
        string $travelMode = 'car'
    ): array {
        if (! $this->isProviderEnabled()) {
            return $this->disabledPayload();
        }

        $apiKey = $this->apiKey();
        if ($apiKey === '') {
            return $this->unconfiguredPayload();
        }

        $coords = $this->normalizeCoordinateList($rawCoordinates);
        if (count($coords) < 2) {
            return $this->errorPayload(
                'INVALID_ROUTE_COORDINATES',
                'Minimal dua titik koordinat rute diperlukan untuk analisa lalu lintas jalur.',
                422
            );
        }

        $ttl = $this->ttlSeconds('flow_cache_ttl_seconds');
        $cumDist = $this->computeCumulativeDistances($coords);
        $totalDistanceMeters = (float) $cumDist[count($cumDist) - 1];
        $courierIndex = ($courierLat !== null && $courierLng !== null)
            ? $this->findNearestCoordinateIndex($coords, $courierLat, $courierLng)
            : 0;

        $routeKey = 'tomtom:route_traffic:'.$this->buildRouteSignature($coords);
        $base = $this->readFreshCache($routeKey);
        $source = 'tomtom_cache';

        if ($base === null) {
            $sampleIndices = $this->sampleRouteIndices($cumDist);
            $flowResults = $this->fetchFlowSegmentsBatch($coords, $sampleIndices, $ttl);

            if ($flowResults['fatal_error'] !== null && empty($flowResults['samples'])) {
                return $flowResults['fatal_error'];
            }

            $incidents = $this->fetchCorridorIncidentsForRoute($coords, $cumDist);
            $segments = $this->buildRouteTrafficSegments(
                $coords,
                $cumDist,
                $sampleIndices,
                $flowResults['samples'],
                $incidents
            );

            // Lengkapi nama jalan untuk maksimal 2 segmen padat/macet paling parah agar alert informatif.
            $resolvedNames = 0;
            foreach ($segments as $idx => $seg) {
                if (! ($seg['is_congested'] ?? false)) {
                    continue;
                }
                if (! empty($seg['road_name'])) {
                    continue;
                }
                $pt = $seg['point'] ?? null;
                if (is_array($pt) && is_numeric($pt['lat'] ?? null) && is_numeric($pt['lng'] ?? null)) {
                    $segments[$idx]['road_name'] = $this->resolveRoadName((float) $pt['lat'], (float) $pt['lng']);
                    $resolvedNames++;
                    if ($resolvedNames >= 2) {
                        break;
                    }
                }
            }

            $now = CarbonImmutable::now('UTC');
            $base = [
                'sampled_points_count' => count($sampleIndices),
                'available_samples_count' => $flowResults['available_count'],
                'unknown_samples_count' => $flowResults['unknown_count'],
                'segments' => $segments,
                'incidents' => $incidents,
                'fetched_at' => $now->toIso8601String(),
                'expires_at' => $now->addSeconds($ttl)->toIso8601String(),
            ];

            if ($flowResults['available_count'] > 0 || ! empty($incidents)) {
                Cache::put($routeKey, $base, $now->addSeconds($ttl));
            }
            $source = 'tomtom_live';
        }

        $courierAlongMeters = (float) ($cumDist[$courierIndex] ?? 0.0);
        $segmentsOut = [];
        foreach (($base['segments'] ?? []) as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $startIdx = max(0, min(count($coords) - 1, (int) ($seg['start_index'] ?? 0)));
            $endIdx = max($startIdx, min(count($coords) - 1, (int) ($seg['end_index'] ?? $startIdx)));
            $seg['distance_from_courier_meters'] = round(max(0.0, ($cumDist[$startIdx] ?? 0.0) - $courierAlongMeters), 1);
            $seg['is_ahead'] = $endIdx >= $courierIndex;
            $segmentsOut[] = $seg;
        }

        $incidentsOut = [];
        foreach (($base['incidents'] ?? []) as $inc) {
            if (! is_array($inc)) {
                continue;
            }
            $incIdx = max(0, min(count($coords) - 1, (int) ($inc['route_index'] ?? 0)));
            $inc['distance_from_courier_meters'] = round(max(0.0, ($cumDist[$incIdx] ?? 0.0) - $courierAlongMeters), 1);
            $inc['is_ahead'] = $incIdx >= $courierIndex;
            $incidentsOut[] = $inc;
        }

        $closestCongestion = $this->pickClosestCongestionAhead($segmentsOut, $incidentsOut);
        if ($closestCongestion !== null && empty($closestCongestion['road_name'])) {
            $pt = $closestCongestion['point'] ?? null;
            if (is_array($pt) && is_numeric($pt['lat'] ?? null) && is_numeric($pt['lng'] ?? null)) {
                $closestCongestion['road_name'] = $this->resolveRoadName((float) $pt['lat'], (float) $pt['lng']);
            }
        }

        $congestedSegments = array_values(array_filter(
            $segmentsOut,
            static fn (array $s): bool => (bool) ($s['is_congested'] ?? false)
        ));

        $overallStatus = $this->determineOverallRouteStatus(
            $segmentsOut,
            $incidentsOut,
            (int) ($base['available_samples_count'] ?? 0)
        );
        $overallMeta = $this->statusPresentation($overallStatus);

        $estimatedDelaySeconds = 0;
        $hasDelayData = false;
        foreach ($congestedSegments as $seg) {
            if (($seg['is_ahead'] ?? true) && is_numeric($seg['estimated_delay_seconds'] ?? null)) {
                $estimatedDelaySeconds += max(0, (int) $seg['estimated_delay_seconds']);
                $hasDelayData = true;
            }
        }
        if (! $hasDelayData) {
            foreach ($incidentsOut as $inc) {
                if (($inc['is_ahead'] ?? true) && is_numeric($inc['delay_seconds'] ?? null)) {
                    $estimatedDelaySeconds += max(0, (int) $inc['delay_seconds']);
                    $hasDelayData = true;
                }
            }
        }

        $payload = [
            'travel_mode' => $travelMode === 'motorcycle' ? 'motorcycle' : 'car',
            'route_distance_meters' => round($totalDistanceMeters, 1),
            'remaining_distance_meters' => round(max(0.0, $totalDistanceMeters - $courierAlongMeters), 1),
            'courier_index' => $courierIndex,
            'sampled_points_count' => (int) ($base['sampled_points_count'] ?? 0),
            'available_samples_count' => (int) ($base['available_samples_count'] ?? 0),
            'unknown_samples_count' => (int) ($base['unknown_samples_count'] ?? 0),
            'congested_segments_count' => count($congestedSegments),
            'incidents_count' => count($incidentsOut),
            'has_congestion' => ! empty($congestedSegments) || $closestCongestion !== null,
            'has_road_closure' => $overallStatus === 'road_closed',
            'overall_status' => $overallStatus,
            'overall_status_label' => $overallMeta['label'],
            'overall_color' => $overallMeta['color'],
            'overall_color_hex' => $overallMeta['color_hex'],
            'estimated_delay_seconds' => $hasDelayData ? $estimatedDelaySeconds : null,
            'closest_congestion' => $closestCongestion,
            'segments' => $segmentsOut,
            'incidents' => $incidentsOut,
            'fetched_at' => $base['fetched_at'],
            'expires_at' => $base['expires_at'],
        ];

        return $this->finalizeSuccessResponse($payload, $source, $ttl);
    }

    /**
     * Hitung rute utama + rute alternatif berbasis lalu lintas real-time menggunakan
     * TomTom Routing API v1 (`calculateRoute` dengan `traffic=true`, `sectionType=traffic`).
     *
     * Tidak pernah mengganti rute kurir secara otomatis; keputusan tetap di kurir.
     *
     * @return array<string, mixed>
     */
    public function getTrafficAwareRoutes(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng,
        string $travelMode = 'motorcycle',
        int $maxAlternatives = 2
    ): array {
        if (! $this->isProviderEnabled()) {
            return $this->disabledPayload();
        }

        $apiKey = $this->apiKey();
        if ($apiKey === '') {
            return $this->unconfiguredPayload();
        }

        $mode = $travelMode === 'car' ? 'car' : 'motorcycle';
        $alts = max(0, min(3, $maxAlternatives));
        $ttl = $this->ttlSeconds('flow_cache_ttl_seconds');

        $oKey = number_format($originLat, 4, '.', '').','.number_format($originLng, 4, '.', '');
        $dKey = number_format($destLat, 4, '.', '').','.number_format($destLng, 4, '.', '');
        $cacheKey = "tomtom:routing:{$mode}:{$alts}:{$oKey}:{$dKey}";

        $cached = $this->readFreshCache($cacheKey);
        if ($cached !== null) {
            return $this->finalizeSuccessResponse($cached, 'tomtom_cache', $ttl);
        }

        $modesToTry = $mode === 'motorcycle' ? ['motorcycle', 'car'] : ['car'];
        $lastError = null;
        $json = null;
        $usedMode = $mode;

        foreach ($modesToTry as $tryMode) {
            try {
                $url = "{$this->baseUrl()}/routing/1/calculateRoute/{$originLat},{$originLng}:{$destLat},{$destLng}/json";
                $response = Http::timeout($this->timeoutSeconds())
                    ->acceptJson()
                    ->get($url, [
                        'key' => $apiKey,
                        'traffic' => 'true',
                        'computeTravelTimeFor' => 'all',
                        'routeRepresentation' => 'polyline',
                        'sectionType' => 'traffic',
                        'instructionsType' => 'text',
                        'language' => 'id-ID',
                        'travelMode' => $tryMode,
                        'maxAlternatives' => $alts,
                        'departAt' => 'now',
                    ]);

                if (! $response->successful()) {
                    Log::warning('TomTom Routing API failed', [
                        'status' => $response->status(),
                        'mode' => $tryMode,
                    ]);
                    $lastError = $this->errorPayload(
                        'TOMTOM_ROUTING_ERROR',
                        'Gagal menghitung rute lalu lintas TomTom (HTTP '.$response->status().').',
                        $response->status() === 429 ? 429 : 502
                    );
                    if ($response->status() === 400 && $tryMode === 'motorcycle') {
                        continue;
                    }
                    break;
                }

                $decoded = $response->json();
                if (! is_array($decoded) || empty($decoded['routes']) || ! is_array($decoded['routes'])) {
                    $lastError = $this->errorPayload(
                        'TOMTOM_ROUTING_EMPTY',
                        'TomTom tidak mengembalikan jalur rute untuk koordinat ini.',
                        502
                    );
                    continue;
                }

                $json = $decoded;
                $usedMode = $tryMode;
                break;
            } catch (\Throwable $e) {
                Log::warning('TomTom Routing exception', [
                    'error' => $this->sanitizeErrorMessage($e->getMessage()),
                ]);
                $lastError = $this->errorPayload(
                    'TOMTOM_CONNECTION_ERROR',
                    'Koneksi ke layanan routing TomTom gagal atau timeout.',
                    504
                );
                break;
            }
        }

        if ($json === null) {
            return $lastError ?? $this->errorPayload(
                'TOMTOM_ROUTING_ERROR',
                'Gagal memuat rute alternatif TomTom.',
                502
            );
        }

        $parsedRoutes = [];
        foreach ($json['routes'] as $routeIndex => $rawRoute) {
            if (! is_array($rawRoute)) {
                continue;
            }
            $parsed = $this->parseTomTomRouteOption($rawRoute, count($parsedRoutes));
            if ($parsed !== null) {
                $parsedRoutes[] = $parsed;
            }
        }

        if (empty($parsedRoutes)) {
            return $this->errorPayload(
                'TOMTOM_INVALID_RESPONSE',
                'Format polyline rute TomTom tidak valid.',
                502
            );
        }

        $primaryTime = (int) $parsedRoutes[0]['travel_time_seconds'];
        $primaryDelay = (int) ($parsedRoutes[0]['traffic_delay_seconds'] ?? 0);
        $recommendedIndex = null;
        $bestSavedSeconds = 0;

        foreach ($parsedRoutes as $idx => $opt) {
            $saved = $primaryTime - (int) $opt['travel_time_seconds'];
            $delayReduction = $primaryDelay - (int) ($opt['traffic_delay_seconds'] ?? 0);
            $parsedRoutes[$idx]['time_saved_seconds'] = $saved;
            $parsedRoutes[$idx]['delay_reduction_seconds'] = $delayReduction;
            $parsedRoutes[$idx]['is_recommended'] = false;

            if ($idx > 0 && ($saved >= 60 || ($delayReduction >= 90 && $saved >= 0)) && $saved > $bestSavedSeconds) {
                $bestSavedSeconds = $saved;
                $recommendedIndex = $idx;
            }
        }

        if ($recommendedIndex !== null) {
            $parsedRoutes[$recommendedIndex]['is_recommended'] = true;
        }

        $now = CarbonImmutable::now('UTC');
        $data = [
            'routing_engine' => 'tomtom_routing_v1',
            'travel_mode' => $usedMode,
            'auto_switch' => false,
            'has_faster_alternative' => $recommendedIndex !== null,
            'recommended_route_index' => $recommendedIndex,
            'routes_count' => count($parsedRoutes),
            'routes' => $parsedRoutes,
            'fetched_at' => $now->toIso8601String(),
            'expires_at' => $now->addSeconds($ttl)->toIso8601String(),
        ];

        Cache::put($cacheKey, $data, $now->addSeconds($ttl));

        return $this->finalizeSuccessResponse($data, 'tomtom_live', $ttl);
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
        if (! $this->isProviderEnabled()) {
            return $this->disabledPayload();
        }

        $apiKey = $this->apiKey();
        if ($apiKey === '') {
            return $this->unconfiguredPayload();
        }

        $ttl = $this->ttlSeconds('incidents_cache_ttl_seconds');
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
        if (! $this->isProviderEnabled()) {
            return $this->disabledPayload();
        }

        $apiKey = $this->apiKey();
        if ($apiKey === '') {
            return $this->unconfiguredPayload();
        }

        $style = in_array($style, self::FLOW_STYLES, true) ? $style : 'relative0';
        $tileSize = $tileSize === 512 ? 512 : 256;
        $thickness = max(1, min(20, $thickness));
        $ttl = $this->ttlSeconds('tile_cache_ttl_seconds');

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
        if (! $this->isProviderEnabled()) {
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
        $ttl = $this->ttlSeconds('flow_cache_ttl_seconds');

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

    /**
     * @return array<string, mixed>
     */
    private function credentials(): array
    {
        return app(GatewayCredentialsResolver::class)->tomtom();
    }

    private function isProviderEnabled(): bool
    {
        return MapService::isEnabled() && (bool) ($this->credentials()['enabled'] ?? true);
    }

    private function apiKey(): string
    {
        return trim((string) ($this->credentials()['api_key'] ?? ''));
    }

    private function baseUrl(): string
    {
        return rtrim((string) ($this->credentials()['base_url'] ?? 'https://api.tomtom.com'), '/');
    }

    private function timeoutSeconds(): int
    {
        return max(3, min(20, (int) ($this->credentials()['timeout_seconds'] ?? 10)));
    }

    private function ttlSeconds(string $configKey): int
    {
        $creds = $this->credentials();
        if (! empty($creds['has_db_cache_ttl'])) {
            return max(15, (int) ($creds['cache_ttl_seconds'] ?? 60));
        }

        return max(15, (int) config("services.tomtom.{$configKey}", 60));
    }

    private function sanitizeSecretString(string $message, string $secret): string
    {
        return $secret !== '' ? str_replace($secret, '[REDACTED]', $message) : $message;
    }

    private function sanitizeErrorMessage(string $message): string
    {
        return $this->sanitizeSecretString($message, $this->apiKey());
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
            'message' => 'Layanan lalu lintas TomTom belum dikonfigurasi pada Admin Provider maupun backend (.env TOMTOM_API_KEY).',
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

    private function flowSegmentCacheKey(
        float $lat,
        float $lng,
        int $zoom = 15,
        string $style = 'relative0'
    ): string {
        $latKey = number_format($lat, 4, '.', '');
        $lngKey = number_format($lng, 4, '.', '');

        return "tomtom:flow:{$style}:{$zoom}:{$latKey}:{$lngKey}";
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>|null
     */
    private function buildFlowSegmentData(
        array $json,
        float $lat,
        float $lng,
        int $ttl,
        bool $includeRoadName
    ): ?array {
        $flow = $json['flowSegmentData'] ?? null;
        if (! is_array($flow)) {
            return null;
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

        return [
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
    }

    /**
     * @param  array<int, array<string, mixed>>  $raw
     * @return array<int, array{lat: float, lng: float}>
     */
    private function normalizeCoordinateList(array $raw): array
    {
        $coords = [];
        foreach ($raw as $pt) {
            if (is_array($pt) && is_numeric($pt['lat'] ?? null) && is_numeric($pt['lng'] ?? null)) {
                $coords[] = ['lat' => (float) $pt['lat'], 'lng' => (float) $pt['lng']];
            }
        }

        return $coords;
    }

    /**
     * @param  array<int, array{lat: float, lng: float}>  $coords
     * @return array<int, float>
     */
    private function computeCumulativeDistances(array $coords): array
    {
        $cum = [0.0];
        $count = count($coords);
        for ($i = 1; $i < $count; $i++) {
            $d = $this->haversineMeters(
                $coords[$i - 1]['lat'],
                $coords[$i - 1]['lng'],
                $coords[$i]['lat'],
                $coords[$i]['lng']
            );
            $cum[$i] = $cum[$i - 1] + $d;
        }

        return $cum;
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * (sin($dLng / 2) ** 2);

        return 2 * $r * asin(min(1.0, sqrt($a)));
    }

    /**
     * @param  array<int, array{lat: float, lng: float}>  $coords
     */
    private function findNearestCoordinateIndex(array $coords, float $lat, float $lng): int
    {
        $bestIdx = 0;
        $bestDist = INF;
        foreach ($coords as $i => $pt) {
            $d = $this->haversineMeters($lat, $lng, $pt['lat'], $pt['lng']);
            if ($d < $bestDist) {
                $bestDist = $d;
                $bestIdx = $i;
            }
        }

        return $bestIdx;
    }

    /**
     * @param  array<int, array{lat: float, lng: float}>  $coords
     */
    private function buildRouteSignature(array $coords): string
    {
        $count = count($coords);
        $anchors = [];
        $step = max(1, (int) floor($count / 10));
        for ($i = 0; $i < $count; $i += $step) {
            $anchors[] = number_format($coords[$i]['lat'], 4, '.', '').','.number_format($coords[$i]['lng'], 4, '.', '');
        }
        $last = $coords[$count - 1];
        $anchors[] = number_format($last['lat'], 4, '.', '').','.number_format($last['lng'], 4, '.', '');

        return sha1($count.'|'.implode(';', $anchors));
    }

    /**
     * Pilih hingga 12 titik sampel terdistribusi merata sepanjang jalur rute.
     *
     * @param  array<int, float>  $cumDist
     * @return array<int, int>
     */
    private function sampleRouteIndices(array $cumDist): array
    {
        $n = count($cumDist);
        if ($n <= 2) {
            return range(0, $n - 1);
        }

        $total = $cumDist[$n - 1];
        if ($total < 200.0) {
            return array_values(array_unique([0, (int) floor(($n - 1) / 2), $n - 1]));
        }

        $stepMeters = max(450.0, $total / 11.0);
        $selected = [0];
        $nextTarget = $stepMeters;

        for ($i = 1; $i < $n - 1; $i++) {
            if ($cumDist[$i] >= $nextTarget) {
                $selected[] = $i;
                $nextTarget = $cumDist[$i] + $stepMeters;
                if (count($selected) >= 11) {
                    break;
                }
            }
        }

        if ($selected[count($selected) - 1] !== $n - 1 && ($total - $cumDist[$selected[count($selected) - 1]]) >= 150.0) {
            $selected[] = $n - 1;
        }

        return array_values(array_unique($selected));
    }

    /**
     * Ambil data Traffic Flow Segment secara paralel untuk titik sampel di rute.
     *
     * @param  array<int, array{lat: float, lng: float}>  $coords
     * @param  array<int, int>  $sampleIndices
     * @return array{samples: array<int, array<string, mixed>>, available_count: int, unknown_count: int, fatal_error: ?array<string, mixed>}
     */
    private function fetchFlowSegmentsBatch(array $coords, array $sampleIndices, int $ttl): array
    {
        $apiKey = $this->apiKey();
        $samples = [];
        $misses = [];

        foreach ($sampleIndices as $sampleIdx) {
            $pt = $coords[$sampleIdx];
            $cacheKey = $this->flowSegmentCacheKey($pt['lat'], $pt['lng']);
            $cached = $this->readFreshCache($cacheKey);
            if ($cached !== null) {
                $samples[$sampleIdx] = $cached;
            } else {
                $misses[] = $sampleIdx;
            }
        }

        $fatalError = null;
        if (! empty($misses)) {
            $url = "{$this->baseUrl()}/traffic/services/4/flowSegmentData/relative0/15/json";
            $timeout = $this->timeoutSeconds();
            $responses = Http::pool(function ($pool) use ($misses, $coords, $url, $apiKey, $timeout) {
                foreach ($misses as $idx) {
                    $pt = $coords[$idx];
                    $pool->as((string) $idx)
                        ->timeout($timeout)
                        ->acceptJson()
                        ->get($url, [
                            'key' => $apiKey,
                            'point' => "{$pt['lat']},{$pt['lng']}",
                            'unit' => 'KMPH',
                            'openLr' => 'false',
                        ]);
                }
            });

            $now = CarbonImmutable::now('UTC');
            foreach ($misses as $idx) {
                $resp = $responses[(string) $idx] ?? null;
                $pt = $coords[$idx];
                if ($resp instanceof \Throwable || $resp === null) {
                    $fatalError ??= $this->errorPayload(
                        'TOMTOM_CONNECTION_ERROR',
                        'Koneksi ke layanan lalu lintas TomTom gagal atau timeout.',
                        504
                    );
                    continue;
                }

                if (! $resp->successful()) {
                    $status = $resp->status();
                    $fatalError ??= $this->errorPayload(
                        'TOMTOM_UPSTREAM_ERROR',
                        "Gagal mengambil data lalu lintas jalur dari TomTom (HTTP {$status}).",
                        $status === 429 ? 429 : 502
                    );
                    continue;
                }

                $json = $resp->json();
                $data = is_array($json)
                    ? $this->buildFlowSegmentData($json, $pt['lat'], $pt['lng'], $ttl, false)
                    : null;
                if ($data !== null) {
                    Cache::put(
                        $this->flowSegmentCacheKey($pt['lat'], $pt['lng']),
                        $data,
                        $now->addSeconds($ttl)
                    );
                    $samples[$idx] = $data;
                }
            }
        }

        $availableCount = 0;
        $unknownCount = 0;
        foreach ($sampleIndices as $sampleIdx) {
            $item = $samples[$sampleIdx] ?? null;
            if ($item !== null && ($item['is_available'] ?? false)) {
                $availableCount++;
            } else {
                $unknownCount++;
            }
        }

        return [
            'samples' => $samples,
            'available_count' => $availableCount,
            'unknown_count' => $unknownCount,
            'fatal_error' => $availableCount === 0 && empty($samples) ? $fatalError : null,
        ];
    }

    /**
     * Ambil insiden dari TomTom lalu saring KETAT agar hanya insiden pada jalur rute (<= 120 m) yang masuk.
     *
     * @param  array<int, array{lat: float, lng: float}>  $coords
     * @param  array<int, float>  $cumDist
     * @return array<int, array<string, mixed>>
     */
    private function fetchCorridorIncidentsForRoute(array $coords, array $cumDist): array
    {
        $lats = array_column($coords, 'lat');
        $lngs = array_column($coords, 'lng');
        $minLat = max(-89.9, min($lats) - 0.004);
        $maxLat = min(89.9, max($lats) + 0.004);
        $minLng = max(-179.9, min($lngs) - 0.004);
        $maxLng = min(179.9, max($lngs) + 0.004);

        if (($maxLat - $minLat) > 1.8) {
            $midLat = ($minLat + $maxLat) / 2.0;
            $minLat = $midLat - 0.9;
            $maxLat = $midLat + 0.9;
        }
        if (($maxLng - $minLng) > 1.8) {
            $midLng = ($minLng + $maxLng) / 2.0;
            $minLng = $midLng - 0.9;
            $maxLng = $midLng + 0.9;
        }

        $res = $this->getIncidents($minLat, $minLng, $maxLat, $maxLng, 'id-ID');
        if (! ($res['success'] ?? false)) {
            return [];
        }

        $rawIncidents = $res['data']['incidents'] ?? [];
        $corridor = [];

        foreach ($rawIncidents as $inc) {
            if (! is_array($inc)) {
                continue;
            }
            $pts = [];
            if (! empty($inc['coordinates']) && is_array($inc['coordinates'])) {
                foreach ($inc['coordinates'] as $c) {
                    if (is_array($c) && is_numeric($c['lat'] ?? null) && is_numeric($c['lng'] ?? null)) {
                        $pts[] = ['lat' => (float) $c['lat'], 'lng' => (float) $c['lng']];
                    }
                }
            }
            if (empty($pts) && is_array($inc['point'] ?? null)) {
                $p = $inc['point'];
                if (is_numeric($p['lat'] ?? null) && is_numeric($p['lng'] ?? null)) {
                    $pts[] = ['lat' => (float) $p['lat'], 'lng' => (float) $p['lng']];
                }
            }
            if (empty($pts)) {
                continue;
            }

            $match = $this->minDistanceToRoutePolyline($pts, $coords);
            if ($match['distance_meters'] > 120.0) {
                continue;
            }

            $routeIdx = $match['nearest_index'];
            $inc['route_distance_meters'] = round($match['distance_meters'], 1);
            $inc['route_index'] = $routeIdx;
            $inc['along_route_meters'] = round((float) ($cumDist[$routeIdx] ?? 0.0), 1);
            $corridor[] = $inc;
        }

        usort($corridor, static fn (array $a, array $b): int => ($a['route_index'] ?? 0) <=> ($b['route_index'] ?? 0));

        return array_slice($corridor, 0, 20);
    }

    /**
     * @param  array<int, array{lat: float, lng: float}>  $points
     * @param  array<int, array{lat: float, lng: float}>  $route
     * @return array{distance_meters: float, nearest_index: int}
     */
    private function minDistanceToRoutePolyline(array $points, array $route): array
    {
        $minDist = INF;
        $bestIdx = 0;
        $routeCount = count($route);

        foreach ($points as $pt) {
            for ($i = 0; $i < $routeCount - 1; $i++) {
                $d = $this->pointToSegmentDistanceMeters($pt['lat'], $pt['lng'], $route[$i], $route[$i + 1]);
                if ($d < $minDist) {
                    $minDist = $d;
                    $bestIdx = $i;
                }
            }
        }

        return ['distance_meters' => $minDist, 'nearest_index' => $bestIdx];
    }

    /**
     * Jarak titik ke segmen garis dalam meter menggunakan proyeksi lokal equirectangular.
     *
     * @param  array{lat: float, lng: float}  $a
     * @param  array{lat: float, lng: float}  $b
     */
    private function pointToSegmentDistanceMeters(float $pLat, float $pLng, array $a, array $b): float
    {
        $cosLat = cos(deg2rad($pLat));
        $mPerDegLat = 111320.0;
        $mPerDegLng = 111320.0 * max(0.1, $cosLat);

        $ax = ($a['lng'] - $pLng) * $mPerDegLng;
        $ay = ($a['lat'] - $pLat) * $mPerDegLat;
        $bx = ($b['lng'] - $pLng) * $mPerDegLng;
        $by = ($b['lat'] - $pLat) * $mPerDegLat;

        $dx = $bx - $ax;
        $dy = $by - $ay;
        $lenSq = $dx * $dx + $dy * $dy;
        if ($lenSq <= 1e-6) {
            return sqrt($ax * $ax + $ay * $ay);
        }

        $t = max(0.0, min(1.0, (-$ax * $dx - $ay * $dy) / $lenSq));
        $cx = $ax + $t * $dx;
        $cy = $ay + $t * $dy;

        return sqrt($cx * $cx + $cy * $cy);
    }

    /**
     * Petakan hasil sampel Flow Segment dan insiden koridor tepat ke rentang indeks polyline
     * sehingga hanya bagian jalan yang benar-benar padat/macet yang berubah warna.
     *
     * @param  array<int, array{lat: float, lng: float}>  $coords
     * @param  array<int, float>  $cumDist
     * @param  array<int, int>  $sampleIndices
     * @param  array<int, array<string, mixed>>  $samples
     * @param  array<int, array<string, mixed>>  $incidents
     * @return array<int, array<string, mixed>>
     */
    private function buildRouteTrafficSegments(
        array $coords,
        array $cumDist,
        array $sampleIndices,
        array $samples,
        array $incidents
    ): array {
        $n = count($coords);
        $rawSegments = [];
        $countSamples = count($sampleIndices);

        foreach ($sampleIndices as $pos => $sampleIdx) {
            $sample = $samples[$sampleIdx] ?? null;
            $prevBound = $pos === 0 ? 0 : (int) floor(($sampleIndices[$pos - 1] + $sampleIdx) / 2);
            $nextBound = $pos === $countSamples - 1 ? $n - 1 : (int) floor(($sampleIdx + $sampleIndices[$pos + 1]) / 2);

            if ($sample === null) {
                $unknownMeta = self::classifyCongestion(null, null);
                $rawSegments[] = [
                    'start_index' => $sampleIdx,
                    'end_index' => min($n - 1, $sampleIdx + 1),
                    'status' => $unknownMeta['status'],
                    'status_label' => $unknownMeta['label'],
                    'color' => $unknownMeta['color'],
                    'color_hex' => $unknownMeta['color_hex'],
                    'speed_ratio_percent' => null,
                    'congestion_percent' => null,
                    'current_speed_kmh' => null,
                    'free_flow_speed_kmh' => null,
                    'estimated_delay_seconds' => null,
                    'is_available' => false,
                    'is_congested' => false,
                    'road_name' => null,
                    'point' => $coords[$sampleIdx],
                    'source_type' => 'flow',
                ];
                continue;
            }

            $flowCoords = is_array($sample['coordinates'] ?? null) ? $sample['coordinates'] : [];
            [$startIdx, $endIdx] = $this->mapSampleToRouteSpan(
                $coords,
                $cumDist,
                $sampleIdx,
                $prevBound,
                $nextBound,
                $flowCoords
            );

            $curTt = $sample['current_travel_time_seconds'] ?? null;
            $freeTt = $sample['free_flow_travel_time_seconds'] ?? null;
            $delaySec = (is_numeric($curTt) && is_numeric($freeTt))
                ? max(0, (int) $curTt - (int) $freeTt)
                : null;

            $rawSegments[] = [
                'start_index' => $startIdx,
                'end_index' => $endIdx,
                'status' => (string) ($sample['status'] ?? 'unknown'),
                'status_label' => (string) ($sample['status_label'] ?? 'Data Tidak Tersedia'),
                'color' => (string) ($sample['color'] ?? 'gray'),
                'color_hex' => (string) ($sample['color_hex'] ?? '#64748B'),
                'speed_ratio_percent' => $sample['speed_ratio_percent'] ?? null,
                'congestion_percent' => $sample['congestion_percent'] ?? null,
                'current_speed_kmh' => $sample['current_speed_kmh'] ?? null,
                'free_flow_speed_kmh' => $sample['free_flow_speed_kmh'] ?? null,
                'estimated_delay_seconds' => $delaySec,
                'is_available' => (bool) ($sample['is_available'] ?? false),
                'is_congested' => (bool) ($sample['is_congested'] ?? false),
                'road_name' => $sample['road_name'] ?? null,
                'point' => $coords[$sampleIdx],
                'source_type' => 'flow',
            ];
        }

        // Petakan insiden serius pada koridor rute ke segmen indeks polyline.
        foreach ($incidents as $inc) {
            $category = (string) ($inc['category'] ?? '');
            $severity = (string) ($inc['severity'] ?? '');
            $status = match (true) {
                $category === 'road_closed' => 'road_closed',
                $severity === 'major' || $severity === 'closure' => 'congested',
                $severity === 'moderate' => 'heavy',
                $category === 'accident' || $category === 'flooding' || $category === 'lane_closed' => 'heavy',
                default => null,
            };
            if ($status === null) {
                continue;
            }

            $meta = $this->statusPresentation($status);
            $rIdx = max(0, min($n - 1, (int) ($inc['route_index'] ?? 0)));
            $startIdx = $rIdx;
            $endIdx = min($n - 1, $rIdx + 1);

            $incCoords = is_array($inc['coordinates'] ?? null) ? $inc['coordinates'] : [];
            if (count($incCoords) >= 2) {
                $firstIdx = $this->findNearestCoordinateIndex($coords, (float) $incCoords[0]['lat'], (float) $incCoords[0]['lng']);
                $lastCoord = $incCoords[count($incCoords) - 1];
                $lastIdx = $this->findNearestCoordinateIndex($coords, (float) $lastCoord['lat'], (float) $lastCoord['lng']);
                $startIdx = min($firstIdx, $lastIdx);
                $endIdx = max($firstIdx, $lastIdx, min($n - 1, $startIdx + 1));
            } else {
                while ($startIdx > 0 && ($cumDist[$rIdx] - $cumDist[$startIdx - 1]) <= 120.0) {
                    $startIdx--;
                }
                while ($endIdx < $n - 1 && ($cumDist[$endIdx + 1] - $cumDist[$rIdx]) <= 120.0) {
                    $endIdx++;
                }
            }

            $rawSegments[] = [
                'start_index' => $startIdx,
                'end_index' => $endIdx,
                'status' => $status,
                'status_label' => $meta['label'],
                'color' => $meta['color'],
                'color_hex' => $meta['color_hex'],
                'speed_ratio_percent' => $status === 'road_closed' ? 0.0 : null,
                'congestion_percent' => $status === 'road_closed' ? 100.0 : null,
                'current_speed_kmh' => null,
                'free_flow_speed_kmh' => null,
                'estimated_delay_seconds' => $inc['delay_seconds'] ?? null,
                'is_available' => true,
                'is_congested' => true,
                'road_name' => $inc['from'] ?? $inc['road_label'] ?? null,
                'point' => $inc['point'] ?? $coords[$startIdx],
                'source_type' => 'incident',
            ];
        }

        usort($rawSegments, function (array $a, array $b): int {
            $cmp = $a['start_index'] <=> $b['start_index'];

            return $cmp !== 0 ? $cmp : ($this->statusRank($b['status']) <=> $this->statusRank($a['status']));
        });

        // Gabungkan segmen berdekatan dengan status kemacetan identik; jangan pernah gabung
        // segmen macet ke segmen lancar.
        $merged = [];
        foreach ($rawSegments as $seg) {
            if (empty($merged)) {
                $merged[] = $seg;
                continue;
            }
            $lastIdx = count($merged) - 1;
            $prev = $merged[$lastIdx];
            if ($prev['status'] === $seg['status'] && $seg['start_index'] <= $prev['end_index'] + 1) {
                $merged[$lastIdx]['end_index'] = max($prev['end_index'], $seg['end_index']);
                if (empty($merged[$lastIdx]['road_name']) && ! empty($seg['road_name'])) {
                    $merged[$lastIdx]['road_name'] = $seg['road_name'];
                }
                if ($seg['current_speed_kmh'] !== null && ($prev['current_speed_kmh'] === null || $seg['current_speed_kmh'] < $prev['current_speed_kmh'])) {
                    $merged[$lastIdx]['current_speed_kmh'] = $seg['current_speed_kmh'];
                    $merged[$lastIdx]['free_flow_speed_kmh'] = $seg['free_flow_speed_kmh'];
                    $merged[$lastIdx]['speed_ratio_percent'] = $seg['speed_ratio_percent'];
                    $merged[$lastIdx]['congestion_percent'] = $seg['congestion_percent'];
                }
                if (is_numeric($seg['estimated_delay_seconds'] ?? null)) {
                    $merged[$lastIdx]['estimated_delay_seconds'] =
                        (int) ($merged[$lastIdx]['estimated_delay_seconds'] ?? 0) + (int) $seg['estimated_delay_seconds'];
                }
                continue;
            }
            $merged[] = $seg;
        }

        return $merged;
    }

    /**
     * @param  array<int, array{lat: float, lng: float}>  $coords
     * @param  array<int, float>  $cumDist
     * @param  array<int, array{lat: float, lng: float}>  $flowSegmentCoords
     * @return array{0: int, 1: int}
     */
    private function mapSampleToRouteSpan(
        array $coords,
        array $cumDist,
        int $sampleIdx,
        int $minBound,
        int $maxBound,
        array $flowSegmentCoords
    ): array {
        $n = count($coords);
        $sampleDist = $cumDist[$sampleIdx];
        $start = $sampleIdx;
        $end = min($n - 1, $sampleIdx + 1);

        if (count($flowSegmentCoords) >= 2) {
            $s = $sampleIdx;
            while ($s > $minBound) {
                $prev = $coords[$s - 1];
                $d = $this->minDistanceToRoutePolyline([$prev], $flowSegmentCoords)['distance_meters'];
                if ($d > 90.0 || ($sampleDist - $cumDist[$s - 1]) > 750.0) {
                    break;
                }
                $s--;
            }
            $e = $sampleIdx;
            while ($e < $maxBound) {
                $next = $coords[$e + 1];
                $d = $this->minDistanceToRoutePolyline([$next], $flowSegmentCoords)['distance_meters'];
                if ($d > 90.0 || ($cumDist[$e + 1] - $sampleDist) > 750.0) {
                    break;
                }
                $e++;
            }
            $start = $s;
            $end = max($s + 1, $e);
            $end = min($n - 1, $end);
        }

        if ($end <= $start) {
            while ($start > $minBound && ($sampleDist - $cumDist[$start - 1]) <= 300.0) {
                $start--;
            }
            while ($end < $maxBound && ($cumDist[$end + 1] - $sampleDist) <= 300.0) {
                $end++;
            }
            if ($end <= $start) {
                $start = max(0, $sampleIdx - 1);
                $end = min($n - 1, max($start + 1, $sampleIdx + 1));
            }
        }

        return [$start, min($n - 1, $end)];
    }

    /**
     * @param  array<int, array<string, mixed>>  $segments
     * @param  array<int, array<string, mixed>>  $incidents
     * @return array<string, mixed>|null
     */
    private function pickClosestCongestionAhead(array $segments, array $incidents): ?array
    {
        $best = null;
        $bestScoreDist = INF;

        foreach ($segments as $seg) {
            if (! ($seg['is_congested'] ?? false) || ! ($seg['is_ahead'] ?? false)) {
                continue;
            }
            $d = (float) ($seg['distance_from_courier_meters'] ?? 0.0);
            if ($d < $bestScoreDist || ($d === $bestScoreDist && $this->statusRank($seg['status']) > $this->statusRank((string) ($best['status'] ?? '')))) {
                $bestScoreDist = $d;
                $best = [
                    'type' => $seg['source_type'] ?? 'flow',
                    'road_name' => $seg['road_name'] ?? null,
                    'distance_from_courier_meters' => round($d, 1),
                    'status' => $seg['status'],
                    'status_label' => $seg['status_label'],
                    'color' => $seg['color'],
                    'color_hex' => $seg['color_hex'],
                    'current_speed_kmh' => $seg['current_speed_kmh'] ?? null,
                    'free_flow_speed_kmh' => $seg['free_flow_speed_kmh'] ?? null,
                    'speed_ratio_percent' => $seg['speed_ratio_percent'] ?? null,
                    'congestion_percent' => $seg['congestion_percent'] ?? null,
                    'estimated_delay_seconds' => $seg['estimated_delay_seconds'] ?? null,
                    'description' => null,
                    'start_index' => $seg['start_index'],
                    'end_index' => $seg['end_index'],
                    'point' => $seg['point'],
                ];
            }
        }

        foreach ($incidents as $inc) {
            if (! ($inc['is_ahead'] ?? false)) {
                continue;
            }
            $cat = (string) ($inc['category'] ?? '');
            $sev = (string) ($inc['severity'] ?? '');
            $status = match (true) {
                $cat === 'road_closed' => 'road_closed',
                $sev === 'major' || $sev === 'closure' => 'congested',
                $sev === 'moderate' => 'heavy',
                $cat === 'accident' || $cat === 'flooding' || $cat === 'lane_closed' => 'heavy',
                default => null,
            };
            if ($status === null) {
                continue;
            }
            $d = (float) ($inc['distance_from_courier_meters'] ?? 0.0);
            if ($best === null || $d + 80.0 < $bestScoreDist) {
                $meta = $this->statusPresentation($status);
                $bestScoreDist = $d;
                $best = [
                    'type' => 'incident',
                    'road_name' => $inc['from'] ?? $inc['road_label'] ?? null,
                    'distance_from_courier_meters' => round($d, 1),
                    'status' => $status,
                    'status_label' => $meta['label'],
                    'color' => $meta['color'],
                    'color_hex' => $meta['color_hex'],
                    'current_speed_kmh' => $best['current_speed_kmh'] ?? null,
                    'free_flow_speed_kmh' => $best['free_flow_speed_kmh'] ?? null,
                    'speed_ratio_percent' => $best['speed_ratio_percent'] ?? null,
                    'congestion_percent' => $best['congestion_percent'] ?? null,
                    'estimated_delay_seconds' => $inc['delay_seconds'] ?? null,
                    'description' => $inc['description'] ?? null,
                    'start_index' => $inc['route_index'] ?? 0,
                    'end_index' => $inc['route_index'] ?? 0,
                    'point' => $inc['point'] ?? null,
                ];
            }
        }

        return $best;
    }

    /**
     * @param  array<int, array<string, mixed>>  $segments
     * @param  array<int, array<string, mixed>>  $incidents
     */
    private function determineOverallRouteStatus(array $segments, array $incidents, int $availableSamplesCount): string
    {
        $highest = $availableSamplesCount > 0 ? 'smooth' : 'unknown';

        foreach ($segments as $seg) {
            if (! ($seg['is_available'] ?? false)) {
                continue;
            }
            $st = (string) ($seg['status'] ?? 'unknown');
            if ($this->statusRank($st) > $this->statusRank($highest)) {
                $highest = $st;
            }
        }

        foreach ($incidents as $inc) {
            $cat = (string) ($inc['category'] ?? '');
            $sev = (string) ($inc['severity'] ?? '');
            $st = match (true) {
                $cat === 'road_closed' => 'road_closed',
                $sev === 'major' || $sev === 'closure' => 'congested',
                $sev === 'moderate' => 'heavy',
                default => null,
            };
            if ($st !== null && $this->statusRank($st) > $this->statusRank($highest)) {
                $highest = $st;
            }
        }

        return $highest;
    }

    private function statusRank(string $status): int
    {
        return match ($status) {
            'road_closed' => 5,
            'congested' => 4,
            'heavy' => 3,
            'moderate' => 2,
            'smooth' => 1,
            default => 0,
        };
    }

    /**
     * @return array{label: string, color: string, color_hex: string}
     */
    private function statusPresentation(string $status): array
    {
        return match ($status) {
            'road_closed' => ['label' => 'Jalan Ditutup', 'color' => 'red', 'color_hex' => '#DC2626'],
            'congested' => ['label' => 'Macet', 'color' => 'red', 'color_hex' => '#DC2626'],
            'heavy' => ['label' => 'Padat', 'color' => 'orange', 'color_hex' => '#F97316'],
            'moderate' => ['label' => 'Mulai padat', 'color' => 'yellow', 'color_hex' => '#EAB308'],
            'smooth' => ['label' => 'Lancar', 'color' => 'green', 'color_hex' => '#16A34A'],
            default => ['label' => 'Data Tidak Tersedia', 'color' => 'gray', 'color_hex' => '#64748B'],
        };
    }

    /**
     * Parse satu opsi rute dari respons TomTom Routing v1 `calculateRoute`.
     *
     * @param  array<string, mixed>  $rawRoute
     * @return array<string, mixed>|null
     */
    private function parseTomTomRouteOption(array $rawRoute, int $routeIndex): ?array
    {
        $points = [];
        foreach (($rawRoute['legs'] ?? []) as $leg) {
            if (! is_array($leg) || empty($leg['points']) || ! is_array($leg['points'])) {
                continue;
            }
            foreach ($leg['points'] as $pt) {
                if (is_array($pt) && is_numeric($pt['latitude'] ?? null) && is_numeric($pt['longitude'] ?? null)) {
                    $points[] = [
                        'lat' => (float) $pt['latitude'],
                        'lng' => (float) $pt['longitude'],
                    ];
                }
            }
        }

        if (count($points) < 2) {
            return null;
        }

        $summary = is_array($rawRoute['summary'] ?? null) ? $rawRoute['summary'] : [];
        $distanceMeters = is_numeric($summary['lengthInMeters'] ?? null) ? (float) $summary['lengthInMeters'] : 0.0;
        $travelTimeSec = is_numeric($summary['travelTimeInSeconds'] ?? null) ? (int) $summary['travelTimeInSeconds'] : 0;
        $delaySec = is_numeric($summary['trafficDelayInSeconds'] ?? null) ? (int) $summary['trafficDelayInSeconds'] : null;
        $noTrafficSec = is_numeric($summary['noTrafficTravelTimeInSeconds'] ?? null)
            ? (int) $summary['noTrafficTravelTimeInSeconds']
            : null;

        $instructions = is_array($rawRoute['guidance']['instructions'] ?? null)
            ? $rawRoute['guidance']['instructions']
            : [];
        $steps = [];
        $instCount = count($instructions);
        for ($i = 0; $i < $instCount; $i++) {
            $ins = $instructions[$i];
            if (! is_array($ins)) {
                continue;
            }
            $nextIns = ($i + 1 < $instCount && is_array($instructions[$i + 1])) ? $instructions[$i + 1] : null;
            $curOffset = is_numeric($ins['routeOffsetInMeters'] ?? null) ? (float) $ins['routeOffsetInMeters'] : 0.0;
            $nextOffset = ($nextIns && is_numeric($nextIns['routeOffsetInMeters'] ?? null))
                ? (float) $nextIns['routeOffsetInMeters']
                : $distanceMeters;
            $curTt = is_numeric($ins['travelTimeInSeconds'] ?? null) ? (float) $ins['travelTimeInSeconds'] : 0.0;
            $nextTt = ($nextIns && is_numeric($nextIns['travelTimeInSeconds'] ?? null))
                ? (float) $nextIns['travelTimeInSeconds']
                : (float) $travelTimeSec;

            $pt = is_array($ins['point'] ?? null) ? $ins['point'] : null;
            $steps[] = [
                'instruction' => trim((string) ($ins['message'] ?? '')),
                'distance_meters' => max(0.0, round($nextOffset - $curOffset, 1)),
                'duration_seconds' => max(0.0, round($nextTt - $curTt, 1)),
                'road_name' => trim((string) ($ins['street'] ?? '')),
                'maneuver_type' => (string) ($ins['maneuver'] ?? 'STRAIGHT'),
                'route_offset_meters' => $curOffset,
                'location' => ($pt && is_numeric($pt['latitude'] ?? null) && is_numeric($pt['longitude'] ?? null))
                    ? ['lat' => (float) $pt['latitude'], 'lng' => (float) $pt['longitude']]
                    : $points[0],
            ];
        }

        $cumDist = $this->computeCumulativeDistances($points);
        $segments = [];
        $worstStatus = 'smooth';
        foreach (($rawRoute['sections'] ?? []) as $sec) {
            if (! is_array($sec)) {
                continue;
            }
            if (strtoupper((string) ($sec['sectionType'] ?? '')) !== 'TRAFFIC') {
                continue;
            }
            $startIdx = max(0, min(count($points) - 1, (int) ($sec['startPointIndex'] ?? 0)));
            $endIdx = max($startIdx, min(count($points) - 1, (int) ($sec['endPointIndex'] ?? $startIdx)));
            $mapped = $this->mapRoutingTrafficSection($sec, $points, $cumDist, $steps, $startIdx, $endIdx);
            if ($mapped !== null) {
                $segments[] = $mapped;
                if ($this->statusRank($mapped['status']) > $this->statusRank($worstStatus)) {
                    $worstStatus = $mapped['status'];
                }
            }
        }

        $meta = $this->statusPresentation($worstStatus);
        $viaRoad = $this->extractPrimaryViaRoad($steps);

        return [
            'route_index' => $routeIndex,
            'label' => $routeIndex === 0 ? 'Rute Utama' : "Rute Alternatif {$routeIndex}",
            'via_road' => $viaRoad,
            'distance_meters' => round($distanceMeters, 1),
            'travel_time_seconds' => $travelTimeSec,
            'no_traffic_travel_time_seconds' => $noTrafficSec,
            'traffic_delay_seconds' => $delaySec,
            'arrival_time' => $summary['arrivalTime'] ?? null,
            'overall_status' => $worstStatus,
            'overall_status_label' => $meta['label'],
            'overall_color' => $meta['color'],
            'overall_color_hex' => $meta['color_hex'],
            'congested_segments_count' => count(array_filter($segments, static fn (array $s): bool => (bool) ($s['is_congested'] ?? false))),
            'coordinates' => $points,
            'steps' => $steps,
            'segments' => $segments,
        ];
    }

    /**
     * @param  array<string, mixed>  $sec
     * @param  array<int, array{lat: float, lng: float}>  $points
     * @param  array<int, float>  $cumDist
     * @param  array<int, array<string, mixed>>  $steps
     * @return array<string, mixed>|null
     */
    private function mapRoutingTrafficSection(
        array $sec,
        array $points,
        array $cumDist,
        array $steps,
        int $startIdx,
        int $endIdx
    ): ?array {
        $category = strtoupper((string) ($sec['simpleCategory'] ?? ''));
        $mag = $sec['magnitudeOfDelay'] ?? 0;
        $magNorm = is_numeric($mag) ? (int) $mag : strtoupper(trim((string) $mag));

        $status = match (true) {
            $category === 'ROAD_CLOSURE' || $magNorm === 4 || $magNorm === 'UNDEFINED' => 'road_closed',
            $magNorm === 3 || $magNorm === 'MAJOR' => 'congested',
            $magNorm === 2 || $magNorm === 'MODERATE' => 'heavy',
            $magNorm === 1 || $magNorm === 'MINOR' => 'moderate',
            default => null,
        };

        if ($status === null) {
            return null;
        }

        $meta = $this->statusPresentation($status);
        $effectiveSpeed = is_numeric($sec['effectiveSpeedInKmh'] ?? null)
            ? (int) round((float) $sec['effectiveSpeedInKmh'])
            : null;
        $delaySec = is_numeric($sec['delayInSeconds'] ?? null) ? (int) $sec['delayInSeconds'] : null;

        $targetDist = $cumDist[$startIdx] ?? 0.0;
        $roadName = null;
        foreach ($steps as $st) {
            if (($st['route_offset_meters'] ?? 0.0) <= $targetDist + 150.0 && ! empty($st['road_name'])) {
                $roadName = (string) $st['road_name'];
            }
        }

        return [
            'start_index' => $startIdx,
            'end_index' => $endIdx,
            'status' => $status,
            'status_label' => $meta['label'],
            'color' => $meta['color'],
            'color_hex' => $meta['color_hex'],
            'speed_ratio_percent' => $status === 'road_closed' ? 0.0 : null,
            'congestion_percent' => $status === 'road_closed' ? 100.0 : null,
            'current_speed_kmh' => $effectiveSpeed,
            'free_flow_speed_kmh' => null,
            'estimated_delay_seconds' => $delaySec,
            'is_available' => true,
            'is_congested' => true,
            'road_name' => $roadName,
            'point' => $points[$startIdx],
            'source_type' => 'routing_section',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     */
    private function extractPrimaryViaRoad(array $steps): ?string
    {
        $roadDistances = [];
        foreach ($steps as $st) {
            $road = trim((string) ($st['road_name'] ?? ''));
            if ($road === '') {
                continue;
            }
            $roadDistances[$road] = ($roadDistances[$road] ?? 0.0) + (float) ($st['distance_meters'] ?? 0.0);
        }

        if (empty($roadDistances)) {
            return null;
        }

        arsort($roadDistances);
        $topRoad = array_key_first($roadDistances);

        return is_string($topRoad) && $topRoad !== '' ? $topRoad : null;
    }
}
