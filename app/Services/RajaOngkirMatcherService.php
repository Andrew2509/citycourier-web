<?php

namespace App\Services;

use App\Http\Controllers\Api\GeocodingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RajaOngkirMatcherService
{
    protected RajaOngkirService $rajaOngkir;

    public function __construct(RajaOngkirService $rajaOngkir)
    {
        $this->rajaOngkir = $rajaOngkir;
    }

    /**
     * Match reverse geocoded location or coordinates to RajaOngkir Region.
     *
     * @param array $input
     * @return array
     */
    public function match(array $input): array
    {
        $lat = isset($input['lat']) ? (float)$input['lat'] : (isset($input['latitude']) ? (float)$input['latitude'] : null);
        $lng = isset($input['lng']) ? (float)$input['lng'] : (isset($input['longitude']) ? (float)$input['longitude'] : null);

        $village   = $input['village'] ?? null;
        $district  = $input['district'] ?? null;
        $city      = $input['city'] ?? null;
        $province  = $input['province'] ?? null;
        $formatted = $input['formatted_address'] ?? null;
        $street    = $input['street_name'] ?? null;
        $houseNo   = $input['house_number'] ?? null;

        // If village/district/city are missing but coordinates are available, call reverse geocoding
        if ((!$village && !$district && !$city) && ($lat !== null && $lng !== null)) {
            $geoCtrl = new GeocodingController();
            $geoReq = Request::create('/api/geocoding/reverse', 'GET', ['lat' => $lat, 'lng' => $lng]);
            $geoRes = $geoCtrl->reverse($geoReq);
            $geoData = json_decode($geoRes->getContent(), true);

            if (!empty($geoData['success']) && !empty($geoData['data'])) {
                $d = $geoData['data'];
                $village   = $d['village'] ?? null;
                $district  = $d['district'] ?? null;
                $city      = $d['city'] ?? null;
                $province  = $d['province'] ?? null;
                $formatted = $d['formatted_address'] ?? $formatted;
                $street    = $d['street_name'] ?? $street;
                $houseNo   = $d['house_number'] ?? $houseNo;
            }
        }

        $cleanVillage  = $this->cleanName($village);
        $cleanDistrict = $this->cleanName($district);
        $cleanCity     = $this->cleanName($city);
        $cleanProvince = $this->cleanName($province);

        // Clue refinement from formatted address
        if (!empty($formatted)) {
            $fmtLower = mb_strtolower($formatted, 'UTF-8');
            if (empty($cleanProvince)) {
                if (str_contains($fmtLower, 'jawa timur')) $cleanProvince = 'jawa timur';
                elseif (str_contains($fmtLower, 'jawa tengah')) $cleanProvince = 'jawa tengah';
                elseif (str_contains($fmtLower, 'jawa barat')) $cleanProvince = 'jawa barat';
                elseif (str_contains($fmtLower, 'dki jakarta') || str_contains($fmtLower, 'jakarta')) $cleanProvince = 'dki jakarta';
                elseif (str_contains($fmtLower, 'banten')) $cleanProvince = 'banten';
                elseif (str_contains($fmtLower, 'di yogyakarta') || str_contains($fmtLower, 'yogyakarta')) $cleanProvince = 'di yogyakarta';
            }
            if (empty($cleanCity) || $cleanCity === $cleanDistrict) {
                if (str_contains($fmtLower, 'sidoarjo')) $cleanCity = 'sidoarjo';
                elseif (str_contains($fmtLower, 'surabaya')) $cleanCity = 'surabaya';
                elseif (str_contains($fmtLower, 'gresik')) $cleanCity = 'gresik';
                elseif (str_contains($fmtLower, 'malang')) $cleanCity = 'malang';
                elseif (str_contains($fmtLower, 'pasuruan')) $cleanCity = 'pasuruan';
                elseif (str_contains($fmtLower, 'mojokerto')) $cleanCity = 'mojokerto';
            }
        }

        // Attempt 1: Try live Komerce / RajaOngkir API if configured
        if ($this->rajaOngkir->getProvider() === 'komerce' && (!empty($cleanDistrict) || !empty($cleanCity))) {
            try {
                $searchQuery = trim(($cleanDistrict ?: $cleanVillage) . ' ' . $cleanCity);
                $apiRes = $this->rajaOngkir->searchDestination($searchQuery);
                if (!empty($apiRes['data']) && is_array($apiRes['data'])) {
                    $bestCandidate = null;
                    $bestScore = 0;

                    foreach ($apiRes['data'] as $cand) {
                        $candCity = $this->cleanName($cand['city_name'] ?? '');
                        $candDist = $this->cleanName($cand['district_name'] ?? '');
                        $candProv = $this->cleanName($cand['province_name'] ?? '');

                        // STRICT HIERARCHY RULE: Province must match if detected or present in address
                        if (!empty($cleanProvince)) {
                            $provMatch = str_contains($candProv, $cleanProvince) || str_contains($cleanProvince, $candProv);
                            if (!$provMatch && !empty($formatted) && !str_contains(mb_strtolower($formatted), $candProv)) {
                                continue; // Reject candidate from wrong province!
                            }
                        }

                        $score = 0;
                        if (!empty($cleanProvince) && (str_contains($candProv, $cleanProvince) || str_contains($cleanProvince, $candProv))) {
                            $score += 20;
                        }

                        // City matching
                        if (!empty($cleanCity) && (str_contains($candCity, $cleanCity) || str_contains($cleanCity, $candCity))) {
                            $score += 35;
                        } elseif (!empty($formatted) && str_contains(mb_strtolower($formatted), $candCity)) {
                            $score += 30;
                        }

                        // District matching
                        if (!empty($cleanDistrict) && (str_contains($candDist, $cleanDistrict) || str_contains($cleanDistrict, $candDist))) {
                            $score += 35;
                        } elseif (!empty($formatted) && str_contains(mb_strtolower($formatted), $candDist)) {
                            $score += 25;
                        }

                        // Village clue matching
                        if (!empty($cleanVillage)) {
                            $subName = $this->cleanName($cand['subdistrict_name'] ?? '');
                            if (!empty($subName) && (str_contains($subName, $cleanVillage) || str_contains($cleanVillage, $subName))) {
                                $score += 25;
                            }
                        }

                        if ($score > $bestScore && $score >= 50) {
                            $bestScore = $score;
                            $bestCandidate = $cand;
                        }
                    }

                    if ($bestCandidate !== null) {
                        $subId = $bestCandidate['subdistrict_id'] ?? ($bestCandidate['district_id'] ?? ($bestCandidate['id'] ?? null));
                        $cityId = $bestCandidate['city_id'] ?? null;
                        if ($subId || $cityId) {
                            $verifiedZip = $bestCandidate['zip_code'] ?? ($bestCandidate['postal_code'] ?? null);
                            return [
                                'matched'                   => true,
                                'rajaongkir_region_id'      => (string)($subId ?: $cityId),
                                'rajaongkir_subdistrict_id' => (string)($subId ?? ''),
                                'rajaongkir_city_id'        => (string)($cityId ?? ''),
                                'province_id'               => (string)($bestCandidate['province_id'] ?? ''),
                                'subdistrict_name'          => $bestCandidate['subdistrict_name'] ?? ($bestCandidate['district_name'] ?? $district),
                                'district_name'             => $bestCandidate['district_name'] ?? $district,
                                'city_name'                 => $bestCandidate['city_name'] ?? $city,
                                'province_name'             => $bestCandidate['province_name'] ?? $province,
                                'postal_code'               => $verifiedZip,
                                'postal_code_verified'      => !empty($verifiedZip),
                                'full_label'                => implode(', ', array_filter([
                                    $village ?: null,
                                    $bestCandidate['district_name'] ?? $district,
                                    $bestCandidate['city_name'] ?? $city
                                ])),
                                'formatted_address'         => $formatted,
                                'latitude'                  => $lat,
                                'longitude'                 => $lng,
                                'status_badge'              => '✓ Wilayah terdeteksi otomatis',
                                'source'                    => 'komerce_api',
                                'confidence_score'          => $bestScore,
                            ];
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Matcher live search exception: ' . $e->getMessage());
            }
        }

        // Attempt 2: Match against standard RajaOngkir master dataset
        $matched = $this->lookupMasterDataset($cleanVillage, $cleanDistrict, $cleanCity, $cleanProvince, $formatted);
        if ($matched) {
            $matched['formatted_address'] = $formatted ?: $this->buildFallbackAddress($street, $houseNo, $matched['full_label']);
            $matched['latitude']          = $lat;
            $matched['longitude']         = $lng;
            $matched['status_badge']      = '✓ Wilayah terdeteksi otomatis';
            return $matched;
        }

        // If no match found, fallback gracefully with clear notice
        return [
            'matched' => false,
            'message' => 'Wilayah belum dapat dicocokkan secara otomatis. Silakan pilih wilayah secara manual.',
            'detected' => [
                'village'           => $village,
                'district'          => $district,
                'city'              => $city,
                'province'          => $province,
                'formatted_address' => $formatted,
                'latitude'          => $lat,
                'longitude'         => $lng,
            ],
        ];
    }

    /**
     * Clean and normalize location name string.
     */
    public function cleanName(?string $name): string
    {
        if (empty($name)) return '';
        $s = mb_strtolower(trim($name), 'UTF-8');
        $s = preg_replace('/^(kota\s+administrasi|kabupaten\s+administrasi|kota|kabupaten|kab\.|kecamatan|kec\.|kelurahan|kel\.|desa|provinsi|prov\.)\s+/iu', '', $s);
        $s = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $s);
        return trim(preg_replace('/\s+/', ' ', $s));
    }

    /**
     * Lookup master dataset of RajaOngkir cities, districts, and villages.
     */
    protected function lookupMasterDataset(string $cleanVillage, string $cleanDistrict, string $cleanCity, string $cleanProvince, ?string $formatted): ?array
    {
        $dataset = $this->getMasterDataset();

        // 1. Precise Match: Village in District of City
        if (!empty($cleanVillage)) {
            foreach ($dataset as $c) {
                if (!empty($cleanCity) && !str_contains($c['clean_city'], $cleanCity) && !str_contains($cleanCity, $c['clean_city'])) {
                    continue;
                }
                foreach ($c['districts'] as $d) {
                    if (!empty($cleanDistrict) && !str_contains($d['clean_district'], $cleanDistrict) && !str_contains($cleanDistrict, $d['clean_district'])) {
                        continue;
                    }
                    foreach ($d['villages'] as $v) {
                        $cleanV = $this->cleanName($v);
                        if ($cleanV === $cleanVillage || str_contains($cleanVillage, $cleanV) || str_contains($cleanV, $cleanVillage)) {
                            return [
                                'matched'                   => true,
                                'rajaongkir_region_id'      => (string)$d['subdistrict_id'],
                                'rajaongkir_subdistrict_id' => (string)$d['subdistrict_id'],
                                'rajaongkir_city_id'        => (string)$c['city_id'],
                                'province_id'               => (string)$c['province_id'],
                                'subdistrict_name'          => $v,
                                'district_name'             => $d['district_name'],
                                'city_name'                 => $c['city_name'],
                                'province_name'             => $c['province_name'],
                                'full_label'                => "{$v}, {$d['district_name']}, {$c['city_name']}",
                                'source'                    => 'master_village_match',
                            ];
                        }
                    }
                }
            }
        }

        // 2. District Match: District in City
        if (!empty($cleanDistrict)) {
            foreach ($dataset as $c) {
                if (!empty($cleanCity) && !str_contains($c['clean_city'], $cleanCity) && !str_contains($cleanCity, $c['clean_city'])) {
                    continue;
                }
                foreach ($c['districts'] as $d) {
                    if ($d['clean_district'] === $cleanDistrict || str_contains($cleanDistrict, $d['clean_district']) || str_contains($d['clean_district'], $cleanDistrict)) {
                        $label = (!empty($cleanVillage) ? ucwords($cleanVillage) . ', ' : '') . "{$d['district_name']}, {$c['city_name']}";
                        return [
                            'matched'                   => true,
                            'rajaongkir_region_id'      => (string)$d['subdistrict_id'],
                            'rajaongkir_subdistrict_id' => (string)$d['subdistrict_id'],
                            'rajaongkir_city_id'        => (string)$c['city_id'],
                            'province_id'               => (string)$c['province_id'],
                            'subdistrict_name'          => !empty($cleanVillage) ? ucwords($cleanVillage) : $d['district_name'],
                            'district_name'             => $d['district_name'],
                            'city_name'                 => $c['city_name'],
                            'province_name'             => $c['province_name'],
                            'full_label'                => $label,
                            'source'                    => 'master_district_match',
                        ];
                    }
                }
            }
        }

        // 3. Fallback: Search from formatted address string (e.g. contains Semolowaru, Sukolilo, Surabaya)
        if (!empty($formatted)) {
            $formattedLower = mb_strtolower($formatted, 'UTF-8');
            foreach ($dataset as $c) {
                if (!str_contains($formattedLower, $c['clean_city'])) {
                    continue;
                }
                foreach ($c['districts'] as $d) {
                    if (str_contains($formattedLower, $d['clean_district'])) {
                        // Check if any village in this district is in the address
                        $foundVillage = null;
                        foreach ($d['villages'] as $v) {
                            if (str_contains($formattedLower, mb_strtolower($v, 'UTF-8'))) {
                                $foundVillage = $v;
                                break;
                            }
                        }
                        $vilLabel = $foundVillage ?: (!empty($cleanVillage) ? ucwords($cleanVillage) : null);
                        $label = ($vilLabel ? "{$vilLabel}, " : "") . "{$d['district_name']}, {$c['city_name']}";
                        return [
                            'matched'                   => true,
                            'rajaongkir_region_id'      => (string)$d['subdistrict_id'],
                            'rajaongkir_subdistrict_id' => (string)$d['subdistrict_id'],
                            'rajaongkir_city_id'        => (string)$c['city_id'],
                            'province_id'               => (string)$c['province_id'],
                            'subdistrict_name'          => $vilLabel ?: $d['district_name'],
                            'district_name'             => $d['district_name'],
                            'city_name'                 => $c['city_name'],
                            'province_name'             => $c['province_name'],
                            'full_label'                => $label,
                            'source'                    => 'formatted_address_match',
                        ];
                    }
                }
            }
        }

        // 4. City-level Match (if district not in master, match city ID)
        if (!empty($cleanCity)) {
            foreach ($dataset as $c) {
                if ($c['clean_city'] === $cleanCity || str_contains($cleanCity, $c['clean_city']) || str_contains($c['clean_city'], $cleanCity)) {
                    $labelParts = array_filter([
                        !empty($cleanVillage) ? ucwords($cleanVillage) : null,
                        !empty($cleanDistrict) ? ucwords($cleanDistrict) : null,
                        $c['city_name']
                    ]);
                    return [
                        'matched'                   => true,
                        'rajaongkir_region_id'      => (string)$c['city_id'],
                        'rajaongkir_subdistrict_id' => '',
                        'rajaongkir_city_id'        => (string)$c['city_id'],
                        'province_id'               => (string)$c['province_id'],
                        'subdistrict_name'          => !empty($cleanVillage) ? ucwords($cleanVillage) : '',
                        'district_name'             => !empty($cleanDistrict) ? ucwords($cleanDistrict) : '',
                        'city_name'                 => $c['city_name'],
                        'province_name'             => $c['province_name'],
                        'full_label'                => implode(', ', $labelParts),
                        'source'                    => 'master_city_match',
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Build fallback address string.
     */
    protected function buildFallbackAddress(?string $street, ?string $houseNo, string $fullLabel): string
    {
        $parts = [];
        if ($street) {
            $parts[] = $houseNo ? "{$street} No. {$houseNo}" : $street;
        }
        $parts[] = $fullLabel;
        return implode(', ', array_filter($parts));
    }

    /**
     * Get list of provinces (combining live API and fallback dataset).
     */
    public function getProvincesList(): array
    {
        if ($this->rajaOngkir->hasValidKey()) {
            return \Illuminate\Support\Facades\Cache::remember('ro_provinces_list', 3600, function() {
                $live = $this->rajaOngkir->getProvinces();
                if (!empty($live['rajaongkir']['results'])) {
                    return $live['rajaongkir']['results'];
                }
                if (!empty($live['data']) && is_array($live['data'])) {
                    return array_map(function($p) {
                        return [
                            'province_id' => (string)($p['province_id'] ?? ($p['id'] ?? '')),
                            'province'    => $p['province_name'] ?? ($p['name'] ?? ($p['province'] ?? '')),
                        ];
                    }, $live['data']);
                }
                return $this->getDefaultProvinces();
            });
        }

        return $this->getDefaultProvinces();
    }

    /**
     * Default list of Indonesian provinces for instant response.
     */
    protected function getDefaultProvinces(): array
    {
        return [
            ['province_id' => '11', 'province' => 'Jawa Timur'],
            ['province_id' => '6',  'province' => 'DKI Jakarta'],
            ['province_id' => '9',  'province' => 'Jawa Barat'],
            ['province_id' => '10', 'province' => 'Jawa Tengah'],
            ['province_id' => '5',  'province' => 'DI Yogyakarta'],
            ['province_id' => '3',  'province' => 'Banten'],
            ['province_id' => '1',  'province' => 'Bali'],
        ];
    }

    /**
     * Get list of cities for a province.
     */
    public function getCitiesList($provinceId = null): array
    {
        if ($this->rajaOngkir->hasValidKey()) {
            $live = $this->rajaOngkir->getCities($provinceId);
            if (!empty($live['rajaongkir']['results'])) {
                return $live['rajaongkir']['results'];
            }
            if (!empty($live['data']) && is_array($live['data'])) {
                return array_map(function($c) {
                    return [
                        'city_id'     => (string)($c['city_id'] ?? ($c['id'] ?? '')),
                        'city_name'   => $c['city_name'] ?? ($c['name'] ?? ''),
                        'type'        => $c['type'] ?? 'Kota',
                        'province_id' => (string)($c['province_id'] ?? ''),
                    ];
                }, $live['data']);
            }
        }

        $res = [];
        foreach ($this->getMasterDataset() as $c) {
            if (!$provinceId || (string)$c['province_id'] === (string)$provinceId) {
                $res[] = [
                    'city_id'     => (string)$c['city_id'],
                    'city_name'   => $c['city_name'],
                    'type'        => 'Kota/Kabupaten',
                    'province_id' => (string)$c['province_id'],
                ];
            }
        }
        return $res;
    }

    /**
     * Get list of districts for a city.
     */
    public function getDistrictsList($cityId): array
    {
        if ($this->rajaOngkir->hasValidKey()) {
            $live = $this->rajaOngkir->getDistricts($cityId);
            if (!empty($live['rajaongkir']['results'])) {
                return $live['rajaongkir']['results'];
            }
            if (!empty($live['data']) && is_array($live['data'])) {
                return array_map(function($d) {
                    return [
                        'subdistrict_id'   => (string)($d['subdistrict_id'] ?? ($d['kecamatan_id'] ?? ($d['id'] ?? ''))),
                        'subdistrict_name' => $d['subdistrict_name'] ?? ($d['kecamatan_name'] ?? ($d['name'] ?? '')),
                        'city_id'          => (string)($d['city_id'] ?? ''),
                    ];
                }, $live['data']);
            }
        }

        $res = [];
        foreach ($this->getMasterDataset() as $c) {
            if ((string)$c['city_id'] === (string)$cityId) {
                foreach ($c['districts'] as $d) {
                    $res[] = [
                        'subdistrict_id'   => (string)$d['subdistrict_id'],
                        'subdistrict_name' => $d['district_name'],
                        'city_id'          => (string)$c['city_id'],
                    ];
                }
            }
        }
        return $res;
    }

    /**
     * Standard RajaOngkir Master Dataset (comprehensive for primary regions & metropolitan cities).
     */
    public function getMasterDataset(): array
    {
        return [
            // KOTA SURABAYA (ID 444)
            [
                'city_id'       => 444,
                'city_name'     => 'Surabaya',
                'clean_city'    => 'surabaya',
                'province_id'   => 11,
                'province_name' => 'Jawa Timur',
                'districts'     => [
                    [
                        'subdistrict_id'   => 6041,
                        'district_name'    => 'Sukolilo',
                        'clean_district'   => 'sukolilo',
                        'villages'         => ['Semolowaru', 'Gebang Putih', 'Keputih', 'Klampis Ngasem', 'Medokan Semampir', 'Menur Pumpungan', 'Nginden Jangkungan'],
                    ],
                    [
                        'subdistrict_id'   => 6035,
                        'district_name'    => 'Gubeng',
                        'clean_district'   => 'gubeng',
                        'villages'         => ['Airlangga', 'Baratajaya', 'Gubeng', 'Kertajaya', 'Mojo', 'Pucang Sewu'],
                    ],
                    [
                        'subdistrict_id'   => 6046,
                        'district_name'    => 'Wonokromo',
                        'clean_district'   => 'wonokromo',
                        'villages'         => ['Darmo', 'Jagir', 'Ngagel', 'Ngagelrejo', 'Sawunggaling', 'Wonokromo'],
                    ],
                    [
                        'subdistrict_id'   => 6038,
                        'district_name'    => 'Rungkut',
                        'clean_district'   => 'rungkut',
                        'villages'         => ['Kali Rungkut', 'Rungkut Kidul', 'Medokan Ayu', 'Wonorejo', 'Penjaringansari', 'Kedung Baruk'],
                    ],
                    [
                        'subdistrict_id'   => 6036,
                        'district_name'    => 'Gunung Anyar',
                        'clean_district'   => 'gunung anyar',
                        'villages'         => ['Gunung Anyar', 'Gunung Anyar Tambak', 'Rungkut Menanggal', 'Rungkut Tengah'],
                    ],
                    [
                        'subdistrict_id'   => 6045,
                        'district_name'    => 'Tenggilis Mejoyo',
                        'clean_district'   => 'tenggilis mejoyo',
                        'villages'         => ['Kendangsari', 'Kutisari', 'Panjang Jiwo', 'Tenggilis Mejoyo', 'Prapen'],
                    ],
                    [
                        'subdistrict_id'   => 6044,
                        'district_name'    => 'Tegalsari',
                        'clean_district'   => 'tegalsari',
                        'villages'         => ['Dr. Soetomo', 'Kedungdoro', 'Keputran', 'Tegalsari', 'Wonorejo'],
                    ],
                    [
                        'subdistrict_id'   => 6034,
                        'district_name'    => 'Genteng',
                        'clean_district'   => 'genteng',
                        'villages'         => ['Embong Kaliasin', 'Genteng', 'Kapasari', 'Ketabang', 'Peneleh'],
                    ],
                    [
                        'subdistrict_id'   => 6043,
                        'district_name'    => 'Tambaksari',
                        'clean_district'   => 'tambaksari',
                        'villages'         => ['Gading', 'Dukuh Setro', 'Kapasmadya Barat', 'Pacar Kembang', 'Pacar Keling', 'Ploso', 'Rangkah', 'Tambaksari'],
                    ],
                    [
                        'subdistrict_id'   => 6037,
                        'district_name'    => 'Mulyorejo',
                        'clean_district'   => 'mulyorejo',
                        'villages'         => ['Dukuh Sutorejo', 'Kalijudan', 'Kalisari', 'Kejawan Putih Tambak', 'Manyar Sabrangan', 'Mulyorejo'],
                    ],
                    [
                        'subdistrict_id'   => 6048,
                        'district_name'    => 'Jambangan',
                        'clean_district'   => 'jambangan',
                        'villages'         => ['Jambangan', 'Karah', 'Kebonsari', 'Pagesangan'],
                    ],
                    [
                        'subdistrict_id'   => 6049,
                        'district_name'    => 'Gayungan',
                        'clean_district'   => 'gayungan',
                        'villages'         => ['Gayungan', 'Ketintang', 'Menanggal', 'Dukuh Menanggal'],
                    ],
                    [
                        'subdistrict_id'   => 6047,
                        'district_name'    => 'Wiyung',
                        'clean_district'   => 'wiyung',
                        'villages'         => ['Babatan', 'Balas Klumprik', 'Jajar Tunggal', 'Wiyung'],
                    ],
                    [
                        'subdistrict_id'   => 6033,
                        'district_name'    => 'Dukuh Pakis',
                        'clean_district'   => 'dukuh pakis',
                        'villages'         => ['Dukuh Kupang', 'Dukuh Pakis', 'Gunungsari', 'Pradah Kalikendal'],
                    ],
                    [
                        'subdistrict_id'   => 6040,
                        'district_name'    => 'Sawahan',
                        'clean_district'   => 'sawahan',
                        'villages'         => ['Banyu Urip', 'Kupang Krajan', 'Pakis', 'Patemon', 'Putat Jaya', 'Sawahan'],
                    ],
                    [
                        'subdistrict_id'   => 6050,
                        'district_name'    => 'Karangpilang',
                        'clean_district'   => 'karangpilang',
                        'villages'         => ['Karangpilang', 'Kebraon', 'Kedurus', 'Warugunung'],
                    ],
                    [
                        'subdistrict_id'   => 6056,
                        'district_name'    => 'Sukomanunggal',
                        'clean_district'   => 'sukomanunggal',
                        'villages'         => ['Putat Gede', 'Simomulyo', 'Simomulyo Baru', 'Sonokwijenan', 'Sukomanunggal', 'Tanjungsari'],
                    ],
                    [
                        'subdistrict_id'   => 6055,
                        'district_name'    => 'Tandes',
                        'clean_district'   => 'tandes',
                        'villages'         => ['Balongsari', 'Banjar Sugihan', 'Karang Poh', 'Manukan Kulon', 'Manukan Wetan', 'Tandes'],
                    ],
                    [
                        'subdistrict_id'   => 6039,
                        'district_name'    => 'Sambikerep',
                        'clean_district'   => 'sambikerep',
                        'villages'         => ['Benowo', 'Bringin', 'Made', 'Lontar', 'Sambikerep'],
                    ],
                    [
                        'subdistrict_id'   => 6060,
                        'district_name'    => 'Lakarsantri',
                        'clean_district'   => 'lakarsantri',
                        'villages'         => ['Bangkingan', 'Jeruk', 'Lakarsantri', 'Lidah Kulon', 'Lidah Wetan', 'Sumur Welut'],
                    ],
                    [
                        'subdistrict_id'   => 6051,
                        'district_name'    => 'Kenjeran',
                        'clean_district'   => 'kenjeran',
                        'villages'         => ['Bulakbanteng', 'Tambakwedi', 'Tanah Kalikedinding', 'Sidotopo Wetan'],
                    ],
                    [
                        'subdistrict_id'   => 6061,
                        'district_name'    => 'Bulak',
                        'clean_district'   => 'bulak',
                        'villages'         => ['Bulak', 'Kedungcowek', 'Kenjeran', 'Sukolilo Baru'],
                    ],
                    [
                        'subdistrict_id'   => 6052,
                        'district_name'    => 'Semampir',
                        'clean_district'   => 'semampir',
                        'villages'         => ['Ampel', 'Pegirian', 'Sidotopo', 'Ujung', 'Wonokusumo'],
                    ],
                    [
                        'subdistrict_id'   => 6053,
                        'district_name'    => 'Pabean Cantikan',
                        'clean_district'   => 'pabean cantikan',
                        'villages'         => ['Bongkaran', 'Krembangan Utara', 'Nyamplungan', 'Perak Timur', 'Perak Utara'],
                    ],
                    [
                        'subdistrict_id'   => 6054,
                        'district_name'    => 'Krembangan',
                        'clean_district'   => 'krembangan',
                        'villages'         => ['Dupak', 'Kemayoran', 'Krembangan Selatan', 'Morokrembangan', 'Perak Barat'],
                    ],
                    [
                        'subdistrict_id'   => 6032,
                        'district_name'    => 'Bubutan',
                        'clean_district'   => 'bubutan',
                        'villages'         => ['Alun-Alun Contong', 'Bubutan', 'Gundih', 'Jepara', 'Tembok Dukuh'],
                    ],
                    [
                        'subdistrict_id'   => 6042,
                        'district_name'    => 'Simokerto',
                        'clean_district'   => 'simokerto',
                        'villages'         => ['Kapasan', 'Sidodadi', 'Simokerto', 'Simolawang', 'Tambakrejo'],
                    ],
                    [
                        'subdistrict_id'   => 6057,
                        'district_name'    => 'Asemrowo',
                        'clean_district'   => 'asemrowo',
                        'villages'         => ['Asemrowo', 'Genting Kalianak', 'Tambak Sarioso'],
                    ],
                    [
                        'subdistrict_id'   => 6058,
                        'district_name'    => 'Benowo',
                        'clean_district'   => 'benowo',
                        'villages'         => ['Kandangan', 'Romokalisari', 'Sememi', 'Tambak Osowilangun'],
                    ],
                    [
                        'subdistrict_id'   => 6059,
                        'district_name'    => 'Pakal',
                        'clean_district'   => 'pakal',
                        'villages'         => ['Babat Jerawat', 'Benowo', 'Pakal', 'Sumberejo'],
                    ],
                ],
            ],

            // KABUPATEN SIDOARJO (ID 409)
            [
                'city_id'       => 409,
                'city_name'     => 'Sidoarjo',
                'clean_city'    => 'sidoarjo',
                'province_id'   => 11,
                'province_name' => 'Jawa Timur',
                'districts'     => [
                    [
                        'subdistrict_id'   => 5601,
                        'district_name'    => 'Waru',
                        'clean_district'   => 'waru',
                        'villages'         => ['Waru', 'Wedoro', 'Berbek', 'Pepelegi', 'Bungurasih', 'Kureksari', 'Janti', 'Ngingas', 'Medaeng', 'Tambaksawah', 'Tambaksumur', 'Tambakoso', 'Tambakwadung'],
                    ],
                    [
                        'subdistrict_id'   => 5602,
                        'district_name'    => 'Gedangan',
                        'clean_district'   => 'gedangan',
                        'villages'         => ['Gedangan', 'Keboansikep', 'Keboananom', 'Ketajen', 'Sawotratap', 'Semambung', 'Sruni', 'Tebel'],
                    ],
                    [
                        'subdistrict_id'   => 5603,
                        'district_name'    => 'Sedati',
                        'clean_district'   => 'sedati',
                        'villages'         => ['Betro', 'Buncitan', 'Cemandi', 'Kalanganyar', 'Pabean', 'Pepe', 'Pranti', 'Pulungan', 'Sedati Agung', 'Sedati Gede', 'Semampir'],
                    ],
                    [
                        'subdistrict_id'   => 5604,
                        'district_name'    => 'Taman',
                        'clean_district'   => 'taman',
                        'villages'         => ['Bebekan', 'Geluran', 'Kalijaten', 'Ketegan', 'Kletek', 'Krembangan', 'Ngelom', 'Sepanjang', 'Taman', 'Tanjungsari', 'Trosobo', 'Wage', 'Wonocolo'],
                    ],
                    [
                        'subdistrict_id'   => 5606,
                        'district_name'    => 'Sidoarjo',
                        'clean_district'   => 'sidoarjo',
                        'villages'         => ['Banjarbendo', 'Bluru Kidul', 'Cemengbakalan', 'Cemengkalang', 'Gebang', 'Jati', 'Kemiri', 'Lebo', 'Lemahputro', 'Magersari', 'Pekauman', 'Pucang', 'Pucanganom', 'Rangkah Kidul', 'Sarirogo', 'Sekardangan', 'Sidokare', 'Sidoklumpuk', 'Sidokumpul', 'Urangagung'],
                    ],
                    [
                        'subdistrict_id'   => 5607,
                        'district_name'    => 'Buduran',
                        'clean_district'   => 'buduran',
                        'villages'         => ['Banjarkemantren', 'Banjarsari', 'Buduran', 'Dukuhtengah', 'Entalsewu', 'Pagerwojo', 'Prasung', 'Sawohan', 'Sidokepung', 'Siwalanpanji', 'Sukorejo', 'Wadungasih'],
                    ],
                    [
                        'subdistrict_id'   => 5608,
                        'district_name'    => 'Sukodono',
                        'clean_district'   => 'sukodono',
                        'villages'         => ['Anggaswangi', 'Bangsri', 'Cangkringsari', 'Jumputrejo', 'Kebonagung', 'Kloposepuluh', 'Masangan Kulon', 'Masangan Wetan', 'Ngaresrejo', 'Pademonegoro', 'Panjunan', 'Pekarungan', 'Plumbungan', 'Sambungrejo', 'Suko', 'Sukodono', 'Suruh', 'Wilayut'],
                    ],
                    [
                        'subdistrict_id'   => 5605,
                        'district_name'    => 'Candi',
                        'clean_district'   => 'candi',
                        'villages'         => ['Balongdowo', 'Balonggagang', 'Candi', 'Gelam', 'Kalipecabean', 'Karangtanjung', 'Kebonsari', 'Kedungkendo', 'Kedungpeluk', 'Kendalpecabean', 'Klurak', 'Larangan', 'Sepande', 'Sugihwaras', 'Sumokali', 'Sumorame', 'Tenggulunan', 'Urangagung', 'Wedoroklurak'],
                    ],
                    [
                        'subdistrict_id'   => 5609,
                        'district_name'    => 'Krian',
                        'clean_district'   => 'krian',
                        'villages'         => ['Barengkrajan', 'Gamping', 'Jatikalang', 'Jerukgamping', 'Junwangi', 'Katerungan', 'Keboharan', 'Kraton', 'Krian', 'Ponokawan', 'Sedenganmijen', 'Sidomojo', 'Sidomulyo', 'Sidorejo', 'Tambakkemerakan', 'Tempel', 'Tropodo', 'Watugolong'],
                    ],
                ],
            ],

            // KABUPATEN GRESIK (ID 133)
            [
                'city_id'       => 133,
                'city_name'     => 'Gresik',
                'clean_city'    => 'gresik',
                'province_id'   => 11,
                'province_name' => 'Jawa Timur',
                'districts'     => [
                    [
                        'subdistrict_id'   => 1850,
                        'district_name'    => 'Gresik',
                        'clean_district'   => 'gresik',
                        'villages'         => ['Bedilan', 'Gapurosukolilo', 'Karangpoh', 'Kebungson', 'Kemuteran', 'Kroman', 'Lumpur', 'Ngipik', 'Pekelingan', 'Pulopancikan', 'Sidokumpul', 'Sukodono', 'Sukoanyar', 'Tlogopatut', 'Tlogopojok'],
                    ],
                    [
                        'subdistrict_id'   => 1851,
                        'district_name'    => 'Kebomas',
                        'clean_district'   => 'kebomas',
                        'villages'         => ['Dahanrejo', 'Giri', 'Gulomantung', 'Indro', 'Karangkering', 'Kawisanyar', 'Kebomas', 'Kedanyang', 'Kembangan', 'Klangonan', 'Randuagung', 'Segoromadu', 'Sekarkurung', 'Sidomoro', 'Singosari', 'Sukorejo'],
                    ],
                    [
                        'subdistrict_id'   => 1852,
                        'district_name'    => 'Manyar',
                        'clean_district'   => 'manyar',
                        'villages'         => ['Banjarsari', 'Banyuwangi', 'Betoyoguci', 'Betoyokauman', 'Karangrejo', 'Leran', 'Manyar Sidorukun', 'Manyar Sidomukti', 'Manyarejo', 'Morobakung', 'Ngampel', 'Peganden', 'Pejangganan', 'Pongangan', 'Roomo', 'Sembayat', 'Suci', 'Sukomulyo', 'Sumurber', 'Tanggulrejo', 'Tebalo', 'Yosowilangun'],
                    ],
                    [
                        'subdistrict_id'   => 1853,
                        'district_name'    => 'Driyorejo',
                        'clean_district'   => 'driyorejo',
                        'villages'         => ['Bambe', 'Banjaran', 'Cangkir', 'Driyorejo', 'Gadung', 'Karangandong', 'Kesambenwetan', 'Krikilan', 'Mojosarirejo', 'Mulung', 'Petiken', 'Radegansari', 'Sumput', 'Tanjungan', 'Tenaru', 'Wedoroanom'],
                    ],
                    [
                        'subdistrict_id'   => 1854,
                        'district_name'    => 'Menganti',
                        'clean_district'   => 'menganti',
                        'villages'         => ['Betiting', 'Boboh', 'Boteng', 'Bringkang', 'Domas', 'Drancang', 'Gadingwatu', 'Hendrosari', 'Hulaan', 'Kepatihan', 'Labuhan', 'Menganti', 'Mojotengah', 'Pelemwatu', 'Pengalangan', 'Pranti', 'Randupadangan', 'Setro', 'Sidowungu'],
                    ],
                ],
            ],

            // KOTA MALANG (ID 256)
            [
                'city_id'       => 256,
                'city_name'     => 'Malang',
                'clean_city'    => 'malang',
                'province_id'   => 11,
                'province_name' => 'Jawa Timur',
                'districts'     => [
                    ['subdistrict_id' => 3550, 'district_name' => 'Klojen', 'clean_district' => 'klojen', 'villages' => ['Klojen', 'Oro-oro Dowo', 'Rampal Celaket', 'Samaan', 'Kauman']],
                    ['subdistrict_id' => 3551, 'district_name' => 'Lowokwaru', 'clean_district' => 'lowokwaru', 'villages' => ['Jatimulyo', 'Ketawanggede', 'Lowokwaru', 'Mojolangu', 'Sumbersari', 'Dinoyo']],
                    ['subdistrict_id' => 3552, 'district_name' => 'Blimbing', 'clean_district' => 'blimbing', 'villages' => ['Blimbing', 'Arjosari', 'Balearjosari', 'Purwantoro', 'Polehan']],
                    ['subdistrict_id' => 3553, 'district_name' => 'Sukun', 'clean_district' => 'sukun', 'villages' => ['Bandungrejosari', 'Gadang', 'Karangbesuki', 'Kebonsari', 'Sukun']],
                    ['subdistrict_id' => 3554, 'district_name' => 'Kedungkandang', 'clean_district' => 'kedungkandang', 'villages' => ['Buring', 'Cemorokandang', 'Kedungkandang', 'Kotalama', 'Sawojajar']],
                ],
            ],

            // DKI JAKARTA - JAKARTA SELATAN (ID 153)
            [
                'city_id'       => 153,
                'city_name'     => 'Jakarta Selatan',
                'clean_city'    => 'jakarta selatan',
                'province_id'   => 6,
                'province_name' => 'DKI Jakarta',
                'districts'     => [
                    ['subdistrict_id' => 2090, 'district_name' => 'Kebayoran Baru', 'clean_district' => 'kebayoran baru', 'villages' => ['Senayan', 'Gandaria Utara', 'Melawai', 'Gunung']],
                    ['subdistrict_id' => 2091, 'district_name' => 'Kebayoran Lama', 'clean_district' => 'kebayoran lama', 'villages' => ['Cipulir', 'Grogol Selatan', 'Pondok Pinang']],
                    ['subdistrict_id' => 2092, 'district_name' => 'Cilandak', 'clean_district' => 'cilandak', 'villages' => ['Cipete Selatan', 'Gandaria Selatan', 'Lebak Bulus', 'Pondok Labu']],
                    ['subdistrict_id' => 2093, 'district_name' => 'Setiabudi', 'clean_district' => 'setiabudi', 'villages' => ['Karet', 'Kuningan Timur', 'Menteng Atas', 'Setiabudi']],
                    ['subdistrict_id' => 2094, 'district_name' => 'Tebet', 'clean_district' => 'tebet', 'villages' => ['Menteng Dalam', 'Tebet Barat', 'Tebet Timur', 'Kebon Baru']],
                    ['subdistrict_id' => 2095, 'district_name' => 'Mampang Prapatan', 'clean_district' => 'mampang prapatan', 'villages' => ['Bangka', 'Kuningan Barat', 'Pela Mampang']],
                    ['subdistrict_id' => 2096, 'district_name' => 'Pasar Minggu', 'clean_district' => 'pasar minggu', 'villages' => ['Cilandak Timur', 'Jati Padang', 'Ragunan', 'Pejaten Barat']],
                ],
            ],

            // DKI JAKARTA - JAKARTA PUSAT (ID 152)
            [
                'city_id'       => 152,
                'city_name'     => 'Jakarta Pusat',
                'clean_city'    => 'jakarta pusat',
                'province_id'   => 6,
                'province_name' => 'DKI Jakarta',
                'districts'     => [
                    ['subdistrict_id' => 2080, 'district_name' => 'Gambir', 'clean_district' => 'gambir', 'villages' => ['Gambir', 'Kebon Kelapa', 'Petojo Selatan', 'Duri Pulo']],
                    ['subdistrict_id' => 2081, 'district_name' => 'Tanah Abang', 'clean_district' => 'tanah abang', 'villages' => ['Bendungan Hilir', 'Gelora', 'Kampung Bali', 'Kebon Kacang', 'Petamburan']],
                    ['subdistrict_id' => 2082, 'district_name' => 'Menteng', 'clean_district' => 'menteng', 'villages' => ['Cikini', 'Gondangdia', 'Menteng', 'Pegangsaan']],
                ],
            ],

            // KOTA BANDUNG (ID 23)
            [
                'city_id'       => 23,
                'city_name'     => 'Bandung',
                'clean_city'    => 'bandung',
                'province_id'   => 9,
                'province_name' => 'Jawa Barat',
                'districts'     => [
                    ['subdistrict_id' => 310, 'district_name' => 'Coblong', 'clean_district' => 'coblong', 'villages' => ['Dago', 'Lebak Siliwangi', 'Sekeloa', 'Sadang Serang']],
                    ['subdistrict_id' => 311, 'district_name' => 'Cicendo', 'clean_district' => 'cicendo', 'villages' => ['Arjuna', 'Husain Sastranegara', 'Pamoyanan', 'Pasirkaliki']],
                    ['subdistrict_id' => 312, 'district_name' => 'Sumur Bandung', 'clean_district' => 'sumur bandung', 'villages' => ['Braga', 'Kebon Pisang', 'Merdeka', 'Babakan Ciamis']],
                ],
            ],

            // KOTA YOGYAKARTA (ID 501)
            [
                'city_id'       => 501,
                'city_name'     => 'Yogyakarta',
                'clean_city'    => 'yogyakarta',
                'province_id'   => 5,
                'province_name' => 'DI Yogyakarta',
                'districts'     => [
                    ['subdistrict_id' => 6900, 'district_name' => 'Gondokusuman', 'clean_district' => 'gondokusuman', 'villages' => ['Kotabaru', 'Klitren', 'Terban']],
                    ['subdistrict_id' => 6901, 'district_name' => 'Danurejan', 'clean_district' => 'danurejan', 'villages' => ['Bausasran', 'Suryatmajan', 'Tegal Panggung']],
                    ['subdistrict_id' => 6902, 'district_name' => 'Umbulharjo', 'clean_district' => 'umbulharjo', 'villages' => ['Giwangan', 'Pandeyan', 'Semaki', 'Tahunan']],
                ],
            ],

            // KOTA SEMARANG (ID 399)
            [
                'city_id'       => 399,
                'city_name'     => 'Semarang',
                'clean_city'    => 'semarang',
                'province_id'   => 10,
                'province_name' => 'Jawa Tengah',
                'districts'     => [
                    ['subdistrict_id' => 5450, 'district_name' => 'Semarang Tengah', 'clean_district' => 'semarang tengah', 'villages' => ['Kembangsari', 'Pandansari', 'Sekayu', 'Pekunden']],
                    ['subdistrict_id' => 5451, 'district_name' => 'Banyumanik', 'clean_district' => 'banyumanik', 'villages' => ['Banyumanik', 'Gedawang', 'Jabungan', 'Padangsari', 'Srondol Wetan']],
                ],
            ],
        ];
    }
}
