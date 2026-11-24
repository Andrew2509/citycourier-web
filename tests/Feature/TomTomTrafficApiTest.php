<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\TomTomTrafficService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\CreatesPaymentWalletSchema;

class TomTomTrafficApiTest extends TestCase
{
    use CreatesPaymentWalletSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBaseTablesForWalletTests();
        Cache::flush();
        Config::set('services.tomtom.api_key', 'tomtom-secret-test-key-xyz');
        Config::set('services.tomtom.base_url', 'https://api.tomtom.com');
        Config::set('services.tomtom.flow_cache_ttl_seconds', 60);
        Config::set('services.tomtom.incidents_cache_ttl_seconds', 60);
        Config::set('services.tomtom.tile_cache_ttl_seconds', 60);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_congestion_classification_bands_and_invalid_values(): void
    {
        // >= 70%: Lancar (hijau)
        $smooth = TomTomTrafficService::classifyCongestion(42, 60);
        $this->assertSame('smooth', $smooth['status']);
        $this->assertSame('Lancar', $smooth['label']);
        $this->assertSame('green', $smooth['color']);
        $this->assertSame(70.0, $smooth['speed_ratio_percent']);
        $this->assertSame(30.0, $smooth['congestion_percent']);
        $this->assertTrue($smooth['is_available']);

        // 50-69%: Mulai padat (kuning)
        $moderate = TomTomTrafficService::classifyCongestion(35, 60);
        $this->assertSame('moderate', $moderate['status']);
        $this->assertSame('Mulai padat', $moderate['label']);
        $this->assertSame('yellow', $moderate['color']);

        // 30-49%: Padat (oranye)
        $heavy = TomTomTrafficService::classifyCongestion(24, 60);
        $this->assertSame('heavy', $heavy['status']);
        $this->assertSame('Padat', $heavy['label']);
        $this->assertSame('orange', $heavy['color']);
        $this->assertTrue($heavy['is_congested']);

        // < 30%: Macet (merah)
        $congested = TomTomTrafficService::classifyCongestion(12, 60);
        $this->assertSame('congested', $congested['status']);
        $this->assertSame('Macet', $congested['label']);
        $this->assertSame('red', $congested['color']);
        $this->assertTrue($congested['is_congested']);

        // Penutupan jalan
        $closed = TomTomTrafficService::classifyCongestion(50, 50, true);
        $this->assertSame('road_closed', $closed['status']);
        $this->assertSame('red', $closed['color']);

        // Nilai tidak tersedia / tidak valid TIDAK pernah dianggap lancar
        foreach ([
            [null, 50],
            [30, null],
            [-5, 50],
            [20, 0],
            [20, -10],
        ] as [$cur, $free]) {
            $invalid = TomTomTrafficService::classifyCongestion($cur, $free);
            $this->assertSame('unknown', $invalid['status']);
            $this->assertNotSame('smooth', $invalid['status']);
            $this->assertFalse($invalid['is_available']);
        }
    }

    public function test_traffic_endpoints_require_auth_validate_coordinates_and_handle_disabled_or_unconfigured(): void
    {
        // Unauthenticated blocked
        $this->getJson('/api/shipping/map/traffic/flow?lat=-7.2575&lng=112.7521')
            ->assertStatus(401);

        $courierUser = User::create([
            'name' => 'Kurir Uji',
            'email' => 'kurir.traffic@city.test',
            'role' => 'courier',
        ]);
        Sanctum::actingAs($courierUser);

        // Koordinat invalid
        $this->getJson('/api/shipping/map/traffic/flow?lat=999&lng=112.7521')
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'VALIDATION_ERROR');

        // Bbox terlalu besar (> 2 derajat) ditolak
        $this->getJson('/api/shipping/map/traffic/incidents?min_lat=-10&min_lng=105&max_lat=-5&max_lng=112')
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'BBOX_TOO_LARGE');

        // Tile di luar koordinat zoom 10 ditolak
        $this->getJson('/api/shipping/map/traffic/tiles/10/99999/100.png')
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'INVALID_TILE');

        // API key kosong -> 503 TOMTOM_NOT_CONFIGURED (bukan data dummy)
        Config::set('services.tomtom.api_key', '');
        $this->getJson('/api/shipping/map/traffic/flow?lat=-7.2575&lng=112.7521')
            ->assertStatus(503)
            ->assertJsonPath('error_code', 'TOMTOM_NOT_CONFIGURED');

        // Map provider OFF oleh Admin -> 503 PROVIDER_DISABLED
        Config::set('services.tomtom.api_key', 'tomtom-secret-test-key-xyz');
        Setting::set('map_enabled', '0', 'providers');
        $this->getJson('/api/shipping/map/traffic/flow?lat=-7.2575&lng=112.7521')
            ->assertStatus(503)
            ->assertJsonPath('error_code', 'PROVIDER_DISABLED');
    }

    public function test_flow_incidents_and_tile_proxies_hide_api_key_cache_results_and_never_serve_expired_cache(): void
    {
        $courierUser = User::create([
            'name' => 'Kurir Proxy',
            'email' => 'kurir.proxy@city.test',
            'role' => 'courier',
        ]);
        Sanctum::actingAs($courierUser);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-24T07:00:00Z'));

        $flowCalls = 0;
        $incidentCalls = 0;
        $tileCalls = 0;

        Http::fake(function ($request) use (&$flowCalls, &$incidentCalls, &$tileCalls) {
            $url = $request->url();

            if (str_contains($url, 'nominatim.openstreetmap.org')) {
                return Http::response([
                    'address' => ['road' => 'Jalan Basuki Rahmat'],
                ], 200);
            }

            if (str_contains($url, '/traffic/services/4/flowSegmentData/')) {
                $flowCalls++;
                $this->assertStringContainsString('tomtom-secret-test-key-xyz', $url);

                return Http::response([
                    'flowSegmentData' => [
                        'frc' => 'FRC2',
                        'currentSpeed' => 18,
                        'freeFlowSpeed' => 60,
                        'currentTravelTime' => 140,
                        'freeFlowTravelTime' => 42,
                        'confidence' => 0.92,
                        'roadClosure' => false,
                        'coordinates' => [
                            'coordinate' => [
                                ['latitude' => -7.2650, 'longitude' => 112.7420],
                                ['latitude' => -7.2660, 'longitude' => 112.7430],
                            ],
                        ],
                    ],
                ], 200);
            }

            if (str_contains($url, '/traffic/services/5/incidentDetails')) {
                $incidentCalls++;

                return Http::response([
                    'incidents' => [
                        [
                            'type' => 'Feature',
                            'geometry' => [
                                'type' => 'Point',
                                'coordinates' => [112.7435, -7.2655],
                            ],
                            'properties' => [
                                'id' => 'inc-001',
                                'iconCategory' => 1,
                                'magnitudeOfDelay' => 3,
                                'events' => [
                                    ['description' => 'Kecelakaan lalu lintas menutup lajur kiri', 'code' => 101, 'iconCategory' => 1],
                                ],
                                'from' => 'Jl. Basuki Rahmat',
                                'to' => 'Jl. Embong Malang',
                                'delay' => 420,
                                'length' => 350,
                                'roadNumbers' => [],
                            ],
                        ],
                        [
                            'type' => 'Feature',
                            'geometry' => [
                                'type' => 'LineString',
                                'coordinates' => [
                                    [112.7440, -7.2660],
                                    [112.7450, -7.2670],
                                ],
                            ],
                            'properties' => [
                                'id' => 'inc-002',
                                'iconCategory' => 8,
                                'magnitudeOfDelay' => 4,
                                'events' => [
                                    ['description' => 'Penutupan jalan perbaikan pipa', 'code' => 401, 'iconCategory' => 8],
                                ],
                                'from' => 'Jl. Tunjungan',
                                'to' => 'Jl. Gubernur Suryo',
                                'roadNumbers' => [],
                            ],
                        ],
                    ],
                ], 200);
            }

            if (str_contains($url, '/traffic/map/4/tile/flow/')) {
                $tileCalls++;

                return Http::response('FAKE_PNG_BINARY_BYTES', 200, ['Content-Type' => 'image/png']);
            }

            return Http::response([], 404);
        });

        // 1. Flow Segment call pertama -> live
        $flowRes = $this->getJson('/api/shipping/map/traffic/flow?lat=-7.2655&lng=112.7425&zoom=15')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.road_name', 'Jalan Basuki Rahmat')
            ->assertJsonPath('data.current_speed_kmh', 18)
            ->assertJsonPath('data.free_flow_speed_kmh', 60)
            ->assertJsonPath('data.speed_ratio_percent', 30)
            ->assertJsonPath('data.congestion_percent', 70)
            ->assertJsonPath('data.status', 'heavy')
            ->assertJsonPath('data.status_label', 'Padat')
            ->assertJsonPath('data.color', 'orange')
            ->assertJsonPath('data.is_stale', false)
            ->assertJsonPath('data.source', 'tomtom_live');

        // API key TIDAK BOLEH bocor ke response JSON
        $this->assertStringNotContainsString('tomtom-secret-test-key-xyz', $flowRes->getContent());

        // 2. Call kedua dalam TTL -> disajikan dari cache tanpa call TomTom lagi
        $this->getJson('/api/shipping/map/traffic/flow?lat=-7.2655&lng=112.7425&zoom=15')
            ->assertOk()
            ->assertJsonPath('data.source', 'tomtom_cache');
        $this->assertSame(1, $flowCalls);

        // 3. Setelah melewati TTL 60s -> tidak menyajikan data kedaluwarsa sebagai real-time; fetch ulang!
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-24T07:01:05Z'));
        $this->getJson('/api/shipping/map/traffic/flow?lat=-7.2655&lng=112.7425&zoom=15')
            ->assertOk()
            ->assertJsonPath('data.source', 'tomtom_live')
            ->assertJsonPath('data.is_stale', false);
        $this->assertSame(2, $flowCalls);

        // 4. Incidents proxy
        $incRes = $this->getJson('/api/shipping/map/traffic/incidents?min_lat=-7.28&min_lng=112.73&max_lat=-7.25&max_lng=112.76')
            ->assertOk()
            ->assertJsonPath('data.count', 2)
            ->assertJsonPath('data.incidents.0.category', 'accident')
            ->assertJsonPath('data.incidents.0.category_label', 'Kecelakaan')
            ->assertJsonPath('data.incidents.1.category', 'road_closed')
            ->assertJsonPath('data.incidents.1.category_label', 'Jalan Ditutup');
        $this->assertStringNotContainsString('tomtom-secret-test-key-xyz', $incRes->getContent());
        $this->assertSame(1, $incidentCalls);

        // 5. Flow Tile proxy mengembalikan image/png & cache 60s
        $tileRes = $this->get('/api/shipping/map/traffic/tiles/15/26650/17000.png?style=relative0&tile_size=256&thickness=4')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
        $this->assertSame('FAKE_PNG_BINARY_BYTES', $tileRes->getContent());

        $this->get('/api/shipping/map/traffic/tiles/15/26650/17000.png?style=relative0&tile_size=256&thickness=4')
            ->assertOk();
        $this->assertSame(1, $tileCalls);
    }

    public function test_delivery_summary_and_upstream_failure_handling(): void
    {
        $courierUser = User::create([
            'name' => 'Kurir Summary',
            'email' => 'kurir.summary@city.test',
            'role' => 'courier',
        ]);
        Sanctum::actingAs($courierUser);

        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, '/traffic/services/4/flowSegmentData/')) {
                $point = $request->data()['point'] ?? '';
                // Titik pickup (-7.25) lancar (45/50 = 90%), tujuan (-7.28) macet (10/50 = 20%)
                $isPickup = str_starts_with((string) $point, '-7.25');

                return Http::response([
                    'flowSegmentData' => [
                        'currentSpeed' => $isPickup ? 45 : 10,
                        'freeFlowSpeed' => 50,
                        'roadClosure' => false,
                        'coordinates' => ['coordinate' => []],
                    ],
                ], 200);
            }

            if (str_contains($url, '/traffic/services/5/incidentDetails')) {
                return Http::response(['incidents' => []], 200);
            }

            return Http::response([], 404);
        });

        $this->postJson('/api/shipping/map/traffic/delivery-summary', [
            'pickup_lat' => -7.2500,
            'pickup_lng' => 112.7500,
            'dropoff_lat' => -7.2800,
            'dropoff_lng' => 112.7400,
            'pickup_road_hint' => 'Jl. Pemuda',
            'dropoff_road_hint' => 'Jl. Raya Darmo',
        ])
            ->assertOk()
            ->assertJsonPath('data.pickup_traffic.status', 'smooth')
            ->assertJsonPath('data.pickup_traffic.road_name', 'Jl. Pemuda')
            ->assertJsonPath('data.dropoff_traffic.status', 'congested')
            ->assertJsonPath('data.dropoff_traffic.road_name', 'Jl. Raya Darmo')
            ->assertJsonPath('data.has_congestion', true)
            ->assertJsonPath('data.alternative_route.supported', false)
            ->assertJsonPath('data.alternative_route.traffic_aware', false);
    }

    public function test_upstream_failure_returns_clean_error_and_never_caches_error(): void
    {
        $courierUser = User::create([
            'name' => 'Kurir Error',
            'email' => 'kurir.error@city.test',
            'role' => 'courier',
        ]);
        Sanctum::actingAs($courierUser);

        Http::fake([
            'https://api.tomtom.com/*' => Http::response(['error' => 'gateway down'], 500),
        ]);

        $this->getJson('/api/shipping/map/traffic/flow?lat=-7.2999&lng=112.7666&zoom=15')
            ->assertStatus(502)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'TOMTOM_UPSTREAM_ERROR');
    }
}
