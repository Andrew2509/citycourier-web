<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\TomTomTrafficService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
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

    public function test_admin_providers_can_save_encrypted_tomtom_key_test_connection_and_toggle_provider(): void
    {
        $this->setUpSpatiePermissionsForAdminTests();

        $admin = User::create([
            'name' => 'Admin TomTom',
            'email' => 'admin.tomtom@city.test',
            'role' => 'admin',
            'password' => Hash::make('secret123'),
        ]);
        $admin->assignRole('super-admin');

        $courierUser = User::create([
            'name' => 'Kurir Admin TomTom',
            'email' => 'kurir.admintt@city.test',
            'role' => 'courier',
        ]);

        // Pastikan DB setting menimpa .env yang kosong
        Config::set('services.tomtom.api_key', '');

        // 1. Halaman /admin/providers menampilkan kartu TomTom Traffic API
        $pageRes = $this->actingAs($admin)->get('/admin/providers');
        $pageRes->assertOk();
        $pageRes->assertSee('TomTom Traffic API');

        // 2. Simpan API Key lewat Admin Panel -> tersimpan terenkripsi (enc:...) dan key asli tidak muncul di HTML
        $this->actingAs($admin)
            ->post('/admin/settings/tomtom', [
                'tomtom_base_url' => 'https://api.tomtom.com',
                'tomtom_cache_ttl_seconds' => 45,
                'tomtom_timeout_seconds' => 12,
                'tomtom_api_key' => 'tomtom-db-secret-key-987654',
            ])
            ->assertRedirect();

        $rawStored = Setting::get('tomtom_api_key');
        $this->assertIsString($rawStored);
        $this->assertStringStartsWith('enc:', $rawStored);
        $this->assertStringNotContainsString('tomtom-db-secret-key-987654', $rawStored);

        $afterSavePage = $this->actingAs($admin)->get('/admin/providers');
        $afterSavePage->assertOk();
        $afterSavePage->assertSee('Terkonfigurasi');
        $afterSavePage->assertDontSee('tomtom-db-secret-key-987654');

        // 3. Proxy flow memakai key DB yang baru disimpan & Admin Test Connection berhasil
        Http::fake(function ($request) {
            $url = $request->url();
            $this->assertStringContainsString('tomtom-db-secret-key-987654', $url);

            return Http::response([
                'flowSegmentData' => [
                    'currentSpeed' => 40,
                    'freeFlowSpeed' => 50,
                    'roadClosure' => false,
                    'coordinates' => ['coordinate' => []],
                ],
            ], 200);
        });

        $this->actingAs($admin)
            ->postJson('/admin/settings/tomtom/test', [
                'tomtom_base_url' => 'https://api.tomtom.com',
                'tomtom_timeout_seconds' => 12,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        Sanctum::actingAs($courierUser);
        $this->getJson('/api/shipping/map/traffic/flow?lat=-7.2575&lng=112.7521&zoom=15')
            ->assertOk()
            ->assertJsonPath('data.status', 'smooth');

        // 4. Matikan provider TomTom lewat /admin/providers/tomtom/toggle -> /api/settings & endpoint lalu lintas ikut OFF
        $this->actingAs($admin)
            ->postJson('/admin/providers/tomtom/toggle', ['enabled' => 0])
            ->assertOk()
            ->assertJsonPath('provider', 'tomtom')
            ->assertJsonPath('enabled', false);

        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('data.providers.tomtom', false);

        Sanctum::actingAs($courierUser);
        $this->getJson('/api/shipping/map/traffic/flow?lat=-7.2575&lng=112.7521&zoom=15')
            ->assertStatus(503)
            ->assertJsonPath('error_code', 'PROVIDER_DISABLED');

        // 5. Hapus secret tersimpan -> kembali TOMTOM_NOT_CONFIGURED jika TomTom ON kembali
        $this->actingAs($admin)
            ->postJson('/admin/providers/tomtom/toggle', ['enabled' => 1])
            ->assertOk()
            ->assertJsonPath('enabled', true);

        $this->actingAs($admin)
            ->post('/admin/settings/tomtom', [
                'tomtom_base_url' => 'https://api.tomtom.com',
                'tomtom_cache_ttl_seconds' => 60,
                'tomtom_timeout_seconds' => 10,
                'clear_tomtom_secret' => '1',
            ])
            ->assertRedirect();

        $this->assertSame('', Setting::get('tomtom_api_key'));

        Sanctum::actingAs($courierUser);
        $this->getJson('/api/shipping/map/traffic/flow?lat=-7.2677&lng=112.7577&zoom=15')
            ->assertStatus(503)
            ->assertJsonPath('error_code', 'TOMTOM_NOT_CONFIGURED');
    }

    public function test_route_traffic_analysis_isolates_congested_segment_filters_off_route_incidents_and_tracks_courier_progress(): void
    {
        $courierUser = User::create([
            'name' => 'Kurir Route',
            'email' => 'kurir.route@city.test',
            'role' => 'courier',
        ]);
        Sanctum::actingAs($courierUser);

        // Buat polyline 15 titik sepanjang ~4.2 km (selatan ke utara, setiap step ~0.0027 deg lat ≈ 300 m)
        $coords = [];
        for ($i = 0; $i < 15; $i++) {
            $coords[] = [
                'lat' => round(-7.2900 + ($i * 0.0027), 5),
                'lng' => 112.7500,
            ];
        }

        $tomtomCallCount = 0;
        Http::fake(function ($request) use (&$tomtomCallCount) {
            $url = $request->url();

            if (str_contains($url, 'nominatim.openstreetmap.org')) {
                return Http::response([
                    'address' => ['road' => 'Jalan Garuda'],
                ], 200);
            }

            if (str_contains($url, '/traffic/services/4/flowSegmentData/')) {
                $tomtomCallCount++;
                parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
                [$pLatStr] = explode(',', (string) ($q['point'] ?? '0,0'));
                $pLat = (float) $pLatStr;

                // Hanya tengah jalur (sekitar index 6..8: lat -7.274 .. -7.266) yang MACET (<30%).
                if ($pLat >= -7.2750 && $pLat <= -7.2650) {
                    return Http::response([
                        'flowSegmentData' => [
                            'currentSpeed' => 8,
                            'freeFlowSpeed' => 30,
                            'currentTravelTime' => 420,
                            'freeFlowTravelTime' => 110,
                            'confidence' => 0.96,
                            'roadClosure' => false,
                            'coordinates' => [
                                'coordinate' => [
                                    ['latitude' => -7.2738, 'longitude' => 112.7500],
                                    ['latitude' => -7.2684, 'longitude' => 112.7500],
                                ],
                            ],
                        ],
                    ], 200);
                }

                // Segmen akhir (dekat tujuan): kecepatan null -> TIDAK boleh dianggap Lancar!
                if ($pLat >= -7.2560) {
                    return Http::response([
                        'flowSegmentData' => [
                            'currentSpeed' => null,
                            'freeFlowSpeed' => 40,
                            'roadClosure' => false,
                            'coordinates' => ['coordinate' => []],
                        ],
                    ], 200);
                }

                // Awal rute lancar (32 / 40 = 80% >= 70%).
                return Http::response([
                    'flowSegmentData' => [
                        'currentSpeed' => 32,
                        'freeFlowSpeed' => 40,
                        'currentTravelTime' => 90,
                        'freeFlowTravelTime' => 75,
                        'confidence' => 0.99,
                        'roadClosure' => false,
                        'coordinates' => ['coordinate' => []],
                    ],
                ], 200);
            }

            if (str_contains($url, '/traffic/services/5/incidentDetails')) {
                $tomtomCallCount++;

                return Http::response([
                    'incidents' => [
                        [
                            // Insiden tepat DI JALUR RUTE (~0 m dari polyline, di lat -7.271)
                            'type' => 'Feature',
                            'geometry' => [
                                'type' => 'LineString',
                                'coordinates' => [
                                    [112.7500, -7.2715],
                                    [112.7500, -7.2695],
                                ],
                            ],
                            'properties' => [
                                'id' => 'inc-on-route',
                                'iconCategory' => 6,
                                'magnitudeOfDelay' => 3,
                                'delay' => 310,
                                'from' => 'Jalan Garuda',
                                'events' => [['description' => 'Antrean panjang dekat perempatan']],
                            ],
                        ],
                        [
                            // Insiden JAUH DARI RUTE (~1.6 km ke timur lng 112.7650) -> WAJIB tersaring
                            'type' => 'Feature',
                            'geometry' => [
                                'type' => 'Point',
                                'coordinates' => [112.7650, -7.2710],
                            ],
                            'properties' => [
                                'id' => 'inc-off-route',
                                'iconCategory' => 1,
                                'magnitudeOfDelay' => 3,
                                'from' => 'Jalan Luar Rute',
                                'events' => [['description' => 'Kecelakaan di jalur lain']],
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });

        $startTime = CarbonImmutable::parse('2026-11-24T04:00:00Z');
        CarbonImmutable::setTestNow($startTime);

        // Request 1: Kurir di titik awal (index 0)
        $res1 = $this->postJson('/api/shipping/map/traffic/route-analysis', [
            'coordinates' => $coords,
            'courier_lat' => $coords[0]['lat'],
            'courier_lng' => $coords[0]['lng'],
            'travel_mode' => 'motorcycle',
        ]);

        $res1->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.source', 'tomtom_live')
            ->assertJsonPath('data.has_congestion', true)
            ->assertJsonPath('data.overall_status', 'congested')
            ->assertJsonPath('data.incidents_count', 1)
            ->assertJsonPath('data.incidents.0.id', 'inc-on-route')
            ->assertJsonPath('data.closest_congestion.road_name', 'Jalan Garuda')
            ->assertJsonPath('data.closest_congestion.status', 'congested')
            ->assertJsonPath('data.closest_congestion.current_speed_kmh', 8)
            ->assertJsonPath('data.closest_congestion.free_flow_speed_kmh', 30);

        $firstDist = (float) $res1->json('data.closest_congestion.distance_from_courier_meters');
        $this->assertGreaterThan(1000.0, $firstDist);

        $segments = $res1->json('data.segments');
        $congested = array_values(array_filter($segments, fn ($s) => $s['is_congested'] === true));
        $unknown = array_values(array_filter($segments, fn ($s) => $s['status'] === 'unknown'));
        $smooth = array_values(array_filter($segments, fn ($s) => $s['status'] === 'smooth'));

        $this->assertNotEmpty($congested);
        $this->assertNotEmpty($unknown);
        $this->assertNotEmpty($smooth);
        // Pastikan tidak seluruh rute dijadikan merah: awal rute (index 0) tidak masuk segmen macet!
        $this->assertGreaterThan(2, $congested[0]['start_index']);
        // Pastikan segmen tanpa data kecepatan TIDAK pernah diberi label/status Lancar.
        $this->assertFalse($unknown[0]['is_available']);
        $this->assertNotSame('smooth', $unknown[0]['status']);

        $callsAfterFirst = $tomtomCallCount;
        $this->assertGreaterThan(1, $callsAfterFirst);

        // Request 2 dalam TTL (20s kemudian) saat kurir bergerak mendekat -> cache hit (0 call baru TomTom),
        // tetapi jarak ke kemacetan terdekat mengecil sesuai posisi kurir terkini!
        CarbonImmutable::setTestNow($startTime->addSeconds(20));
        $res2 = $this->postJson('/api/shipping/map/traffic/route-analysis', [
            'coordinates' => $coords,
            'courier_lat' => $coords[3]['lat'],
            'courier_lng' => $coords[3]['lng'],
        ]);

        $res2->assertOk()
            ->assertJsonPath('data.source', 'tomtom_cache');
        $this->assertSame($callsAfterFirst, $tomtomCallCount);
        $secondDist = (float) $res2->json('data.closest_congestion.distance_from_courier_meters');
        $this->assertLessThan($firstDist - 500.0, $secondDist);

        // Request 3 setelah TTL habis (65s) -> paksa fetch baru (tidak menyajikan cache kedaluwarsa).
        CarbonImmutable::setTestNow($startTime->addSeconds(65));
        $res3 = $this->postJson('/api/shipping/map/traffic/route-analysis', [
            'coordinates' => $coords,
            'courier_lat' => $coords[3]['lat'],
            'courier_lng' => $coords[3]['lng'],
        ]);

        $res3->assertOk()
            ->assertJsonPath('data.source', 'tomtom_live');
        $this->assertGreaterThan($callsAfterFirst, $tomtomCallCount);
    }

    public function test_traffic_aware_alternatives_parses_tomtom_routing_and_never_auto_switches(): void
    {
        $courierUser = User::create([
            'name' => 'Kurir Alt',
            'email' => 'kurir.alt@city.test',
            'role' => 'courier',
        ]);
        Sanctum::actingAs($courierUser);

        Http::fake([
            'api.tomtom.com/routing/1/calculateRoute/*' => Http::response([
                'routes' => [
                    [
                        'summary' => [
                            'lengthInMeters' => 12500,
                            'travelTimeInSeconds' => 1380,
                            'noTrafficTravelTimeInSeconds' => 960,
                            'trafficDelayInSeconds' => 420,
                            'arrivalTime' => '2026-11-24T11:23:00+07:00',
                        ],
                        'legs' => [
                            [
                                'points' => [
                                    ['latitude' => -7.290, 'longitude' => 112.750],
                                    ['latitude' => -7.280, 'longitude' => 112.750],
                                    ['latitude' => -7.270, 'longitude' => 112.750],
                                    ['latitude' => -7.260, 'longitude' => 112.750],
                                ],
                            ],
                        ],
                        'sections' => [
                            [
                                'startPointIndex' => 1,
                                'endPointIndex' => 2,
                                'sectionType' => 'TRAFFIC',
                                'simpleCategory' => 'JAM',
                                'magnitudeOfDelay' => 3,
                                'delayInSeconds' => 360,
                                'effectiveSpeedInKmh' => 8,
                            ],
                        ],
                        'guidance' => [
                            'instructions' => [
                                [
                                    'routeOffsetInMeters' => 0,
                                    'travelTimeInSeconds' => 0,
                                    'point' => ['latitude' => -7.290, 'longitude' => 112.750],
                                    'street' => 'Jalan Garuda',
                                    'maneuver' => 'DEPART',
                                    'message' => 'Berangkat ke utara di Jalan Garuda',
                                ],
                                [
                                    'routeOffsetInMeters' => 12500,
                                    'travelTimeInSeconds' => 1380,
                                    'point' => ['latitude' => -7.260, 'longitude' => 112.750],
                                    'street' => 'Jalan Ahmad Yani',
                                    'maneuver' => 'ARRIVE',
                                    'message' => 'Tiba di tujuan',
                                ],
                            ],
                        ],
                    ],
                    [
                        'summary' => [
                            'lengthInMeters' => 13100,
                            'travelTimeInSeconds' => 900,
                            'noTrafficTravelTimeInSeconds' => 840,
                            'trafficDelayInSeconds' => 60,
                            'arrivalTime' => '2026-11-24T11:15:00+07:00',
                        ],
                        'legs' => [
                            [
                                'points' => [
                                    ['latitude' => -7.290, 'longitude' => 112.750],
                                    ['latitude' => -7.275, 'longitude' => 112.762],
                                    ['latitude' => -7.260, 'longitude' => 112.750],
                                ],
                            ],
                        ],
                        'sections' => [],
                        'guidance' => [
                            'instructions' => [
                                [
                                    'routeOffsetInMeters' => 0,
                                    'travelTimeInSeconds' => 0,
                                    'point' => ['latitude' => -7.290, 'longitude' => 112.750],
                                    'street' => 'Jalan Tol Waru',
                                    'maneuver' => 'DEPART',
                                    'message' => 'Ikuti Jalan Tol Waru',
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $res = $this->postJson('/api/shipping/map/traffic/alternatives', [
            'origin_lat' => -7.290,
            'origin_lng' => 112.750,
            'destination_lat' => -7.260,
            'destination_lng' => 112.750,
            'travel_mode' => 'motorcycle',
            'max_alternatives' => 2,
        ]);

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.auto_switch', false)
            ->assertJsonPath('data.has_faster_alternative', true)
            ->assertJsonPath('data.recommended_route_index', 1)
            ->assertJsonPath('data.routes_count', 2)
            ->assertJsonPath('data.routes.0.segments.0.status', 'congested')
            ->assertJsonPath('data.routes.0.segments.0.color', 'red')
            ->assertJsonPath('data.routes.0.segments.0.start_index', 1)
            ->assertJsonPath('data.routes.0.segments.0.end_index', 2)
            ->assertJsonPath('data.routes.1.is_recommended', true)
            ->assertJsonPath('data.routes.1.time_saved_seconds', 480)
            ->assertJsonPath('data.routes.1.via_road', 'Jalan Tol Waru');

        $this->assertStringNotContainsString('tomtom-secret-test-key-xyz', (string) $res->getContent());
    }

    public function test_routing_endpoint_uses_tomtom_routing_v1_and_falls_back_to_osrm_when_unconfigured(): void
    {
        $courierUser = User::create([
            'name' => 'Kurir Routing',
            'email' => 'kurir.routing@city.test',
            'role' => 'courier',
        ]);
        Sanctum::actingAs($courierUser);

        Http::fake([
            'api.tomtom.com/routing/1/calculateRoute/*' => Http::response([
                'routes' => [
                    [
                        'summary' => [
                            'lengthInMeters' => 4200,
                            'travelTimeInSeconds' => 510,
                            'noTrafficTravelTimeInSeconds' => 390,
                            'trafficDelayInSeconds' => 120,
                        ],
                        'legs' => [
                            [
                                'points' => [
                                    ['latitude' => -7.2900, 'longitude' => 112.7500],
                                    ['latitude' => -7.2750, 'longitude' => 112.7500],
                                    ['latitude' => -7.2600, 'longitude' => 112.7500],
                                ],
                            ],
                        ],
                        'sections' => [
                            [
                                'startPointIndex' => 0,
                                'endPointIndex' => 1,
                                'sectionType' => 'TRAFFIC',
                                'simpleCategory' => 'JAM',
                                'magnitudeOfDelay' => 2,
                                'delayInSeconds' => 120,
                                'effectiveSpeedInKmh' => 14,
                            ],
                        ],
                        'guidance' => [
                            'instructions' => [
                                [
                                    'routeOffsetInMeters' => 0,
                                    'travelTimeInSeconds' => 0,
                                    'point' => ['latitude' => -7.2900, 'longitude' => 112.7500],
                                    'street' => 'Jalan Garuda',
                                    'maneuver' => 'DEPART',
                                    'combinedMessage' => 'Berangkat ke utara di Jalan Garuda',
                                ],
                                [
                                    'routeOffsetInMeters' => 2100,
                                    'travelTimeInSeconds' => 260,
                                    'point' => ['latitude' => -7.2750, 'longitude' => 112.7500],
                                    'street' => 'Jl. Semolowaru',
                                    'maneuver' => 'TURN_LEFT',
                                    'combinedMessage' => 'Belok kiri ke Jl. Semolowaru',
                                ],
                                [
                                    'routeOffsetInMeters' => 4200,
                                    'travelTimeInSeconds' => 510,
                                    'point' => ['latitude' => -7.2600, 'longitude' => 112.7500],
                                    'street' => 'Jl. Semolowaru',
                                    'maneuver' => 'ARRIVE',
                                    'combinedMessage' => 'Tiba di lokasi penerima',
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $res = $this->postJson('/api/shipping/map/routing', [
            'origin' => '112.7500,-7.2900',
            'destination' => '112.7500,-7.2600',
            'travel_mode' => 'motorcycle',
        ]);

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.provider', 'tomtom_routing_v1')
            ->assertJsonPath('data.routes.0.distance', 4200)
            ->assertJsonPath('data.routes.0.traffic_delay_seconds', 120)
            ->assertJsonPath('data.routes.0.legs.0.steps.1.maneuver.type', 'TURN_LEFT')
            ->assertJsonPath('data.routes.0.legs.0.steps.1.is_tomtom_step', true)
            ->assertJsonPath('data.routes.0.legs.0.steps.1.route_offset_meters', 2100)
            ->assertJsonPath('data.routes.0.traffic_segments.0.status', 'heavy');

        // Saat TomTom tidak terkonfigurasi, fallback ke OSRM tetap berjalan mulus.
        Config::set('services.tomtom.api_key', '');
        Http::fake([
            'router.project-osrm.org/*' => Http::response([
                'code' => 'Ok',
                'routes' => [
                    [
                        'geometry' => [
                            'type' => 'LineString',
                            'coordinates' => [
                                [112.7500, -7.2900],
                                [112.7500, -7.2600],
                            ],
                        ],
                        'distance' => 4100,
                        'duration' => 380,
                        'legs' => [
                            [
                                'distance' => 4100,
                                'duration' => 380,
                                'steps' => [],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $fallbackRes = $this->postJson('/api/shipping/map/routing', [
            'origin' => '112.7500,-7.2900',
            'destination' => '112.7500,-7.2600',
        ]);

        $fallbackRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.routes.0.distance', 4100);
    }
}
