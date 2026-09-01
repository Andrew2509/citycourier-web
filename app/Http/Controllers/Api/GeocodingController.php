<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Geocoding proxy - Nominatim (primary) + Photon (fallback).
 * GET /api/geocoding/search?q=...&limit=5
 * GET /api/geocoding/reverse?lat=...&lng=...
 */
class GeocodingController extends Controller
{
    private const NOMINATIM_BASE = 'https://nominatim.openstreetmap.org';
    private const PHOTON_BASE = 'https://photon.komoot.io';
    private const USER_AGENT = 'CityCourierBackend/1.0 (https://citycourier.pabm.space)';
    private const TIMEOUT = 15;
    private const SEARCH_CACHE_TTL = 300;
    private const REVERSE_CACHE_TTL = 600;

    public function search(Request $request)
    {
        $query = $request->input('q', '');
        $limit = min((int) $request->input('limit', 5), 10);

        if (strlen($query) < 2) {
            return response()->json([
                'success' => false,
                'message' => 'Query must be at least 2 characters.',
            ], 400);
        }

        $cacheKey = 'geo_search_' . strtolower(trim($query)) . "_{$limit}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response()->json([
                'success' => true,
                'data'    => $cached,
                'source'  => 'cache',
            ]);
        }

        // 1) Nominatim with full query
        $nominatimResults = $this->nominatimSearch($query, $limit);

        if (count($nominatimResults) >= 1) {
            Cache::put($cacheKey, $nominatimResults, self::SEARCH_CACHE_TTL);
            return response()->json([
                'success' => true,
                'data'    => $nominatimResults,
                'source'  => 'nominatim',
            ]);
        }

        // 1b) Nominatim with simplified query (first 2 parts + city)
        $simplified = $this->simplifyQuery($query);
        if ($simplified !== $query) {
            $nominatimResults = $this->nominatimSearch($simplified, $limit);
            if (count($nominatimResults) >= 1) {
                Cache::put($cacheKey, $nominatimResults, self::SEARCH_CACHE_TTL);
                return response()->json([
                    'success' => true,
                    'data'    => $nominatimResults,
                    'source'  => 'nominatim_simplified',
                ]);
            }
        }

        // 2) Photon fallback
        $photonResults = $this->photonSearch($query, $limit);
        $merged = $this->deduplicate(array_merge($nominatimResults, $photonResults));

        // 2b) Photon with simplified query
        if (empty($merged) && $simplified !== $query) {
            $photonResults = $this->photonSearch($simplified, $limit);
            $merged = $this->deduplicate(array_merge($nominatimResults, $photonResults));
        }

        if (!empty($merged)) {
            Cache::put($cacheKey, $merged, self::SEARCH_CACHE_TTL);
        }

        return response()->json([
            'success' => true,
            'data'    => $merged,
            'source'  => !empty($nominatimResults) ? 'nominatim+photon' : 'photon',
        ]);
    }

    public function reverse(Request $request)
    {
        $lat = $request->input('lat');
        $lng = $request->input('lng');

        if ($lat === null || $lng === null) {
            return response()->json([
                'success' => false,
                'message' => 'lat and lng are required.',
            ], 400);
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        $cacheKey = 'geo_reverse_' . round($lat, 5) . '_' . round($lng, 5);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response()->json([
                'success' => true,
                'data'    => $cached,
                'source'  => 'cache',
            ]);
        }

        // 1) Nominatim reverse
        $result = $this->nominatimReverse($lat, $lng);
        if ($result !== null) {
            Cache::put($cacheKey, $result, self::REVERSE_CACHE_TTL);
            return response()->json([
                'success' => true,
                'data'    => $result,
                'source'  => 'nominatim',
            ]);
        }

        // 2) Photon reverse fallback
        $result = $this->photonReverse($lat, $lng);
        if ($result !== null) {
            Cache::put($cacheKey, $result, self::REVERSE_CACHE_TTL);
        }

        return response()->json([
            'success' => true,
            'data'    => $result,
            'source'  => $result !== null ? 'photon' : null,
        ]);
    }

    // ── Nominatim ───────────────────────────────────────────────

    private function nominatimSearch(string $query, int $limit): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->get(self::NOMINATIM_BASE . '/search', [
                    'q'              => $query,
                    'format'         => 'json',
                    'addressdetails' => '1',
                    'limit'          => (string) $limit,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                if (is_array($data)) {
                    return array_map([$this, 'mapNominatim'], $data);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Geocoding Nominatim search failed: ' . $e->getMessage());
        }
        return [];
    }

    private function nominatimReverse(float $lat, float $lng): ?array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->get(self::NOMINATIM_BASE . '/reverse', [
                    'lat'            => (string) $lat,
                    'lon'            => (string) $lng,
                    'format'         => 'json',
                    'addressdetails' => '1',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['error'])) return null;
                return $this->mapNominatim($data);
            }
        } catch (\Exception $e) {
            Log::warning('Geocoding Nominatim reverse failed: ' . $e->getMessage());
        }
        return null;
    }

    private function mapNominatim(array $data): array
    {
        $addr = $data['address'] ?? [];
        return [
            'place_id'          => $data['place_id'] ?? null,
            'osm_type'          => $data['osm_type'] ?? null,
            'osm_id'            => $data['osm_id'] ?? null,
            'formatted_address' => $data['display_name'] ?? '',
            'house_number'      => $addr['house_number'] ?? null,
            'street_name'       => $addr['road'] ?? $addr['pedestrian'] ?? $addr['path'] ?? null,
            'neighbourhood'     => $addr['neighbourhood'] ?? $addr['suburb'] ?? null,
            'village'           => $addr['village'] ?? null,
            'district'          => $addr['city_district'] ?? null,
            'city'              => $addr['city'] ?? $addr['town'] ?? $addr['municipality'] ?? null,
            'province'          => $addr['state'] ?? $addr['province'] ?? null,
            'postal_code'       => $addr['postcode'] ?? null,
            'country'           => $addr['country'] ?? null,
            'latitude'          => (float) ($data['lat'] ?? 0),
            'longitude'         => (float) ($data['lon'] ?? 0),
            'location_source'   => 'provider',
        ];
    }

    // ── Photon ──────────────────────────────────────────────────

    private function photonSearch(string $query, int $limit): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->get(self::PHOTON_BASE . '/api', [
                    'q'     => $query,
                    'limit' => (string) $limit,
                    'lang'  => 'id',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $features = $data['features'] ?? [];
                return array_map([$this, 'mapPhoton'], $features);
            }
        } catch (\Exception $e) {
            Log::warning('Geocoding Photon search failed: ' . $e->getMessage());
        }
        return [];
    }

    private function photonReverse(float $lat, float $lng): ?array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->get(self::PHOTON_BASE . '/reverse', [
                    'lat'  => (string) $lat,
                    'lon'  => (string) $lng,
                    'lang' => 'id',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $features = $data['features'] ?? [];
                if (!empty($features)) {
                    return $this->mapPhoton($features[0]);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Geocoding Photon reverse failed: ' . $e->getMessage());
        }
        return null;
    }

    private function mapPhoton(array $feature): array
    {
        $props  = $feature['properties'] ?? [];
        $coords = ($feature['geometry']['coordinates']) ?? [0, 0];

        $street      = $props['street'] ?? null;
        $housenumber = $props['housenumber'] ?? null;
        $city        = $props['city'] ?? null;
        $name        = $props['name'] ?? null;

        $parts = [];
        if ($street) {
            $parts[] = $housenumber ? "$street $housenumber" : $street;
        } elseif ($name) {
            $parts[] = $name;
        }
        if ($city) $parts[] = $city;

        return [
            'place_id'          => $props['osm_id'] ?? null,
            'formatted_address' => !empty($parts) ? implode(', ', $parts) : ($name ?? 'Unknown'),
            'house_number'      => $housenumber,
            'street_name'       => $street ?? $name,
            'neighbourhood'     => $props['neighbourhood'] ?? null,
            'village'           => $props['village'] ?? null,
            'district'          => $props['district'] ?? null,
            'city'              => $city,
            'province'          => $props['state'] ?? null,
            'postal_code'       => $props['postcode'] ?? null,
            'country'           => $props['country'] ?? null,
            'latitude'          => isset($coords[1]) ? (float) $coords[1] : 0.0,
            'longitude'         => isset($coords[0]) ? (float) $coords[0] : 0.0,
            'location_source'   => 'provider',
        ];
    }

    // ── Helpers ─────────────────────────────────────────────────

    private function deduplicate(array $addresses): array
    {
        $seen   = [];
        $unique = [];
        foreach ($addresses as $addr) {
            $key = round($addr['latitude'], 5) . ',' . round($addr['longitude'], 5);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $addr;
            }
        }
        return $unique;
    }

    /**
     * Simplify long address query for geocoding.
     * Take first 2 parts (name + street) + city name.
     * E.g. "Reny Swalayan, No. 132, Jl. X, RW 07, Wonokromo, Surabaya, ..."
     *    -> "Reny Swalayan, No. 132, Surabaya"
     */
    private function simplifyQuery(string $query): string
    {
        $parts = array_map('trim', explode(',', $query));
        $parts = array_filter($parts, fn($p) => strlen($p) > 0);
        $parts = array_values($parts);

        if (count($parts) <= 2) return $query;

        $result = $parts[0];
        if (count($parts) > 1) {
            $result .= ', ' . $parts[1];
        }

        // Find city name and append
        $cities = ['Surabaya','Sidoarjo','Gresik','Mojokerto','Malang','Jakarta','Bandung','Semarang','Yogyakarta','Medan'];
        foreach ($parts as $part) {
            foreach ($cities as $city) {
                if (stripos($part, $city) !== false && stripos($result, $city) === false) {
                    $result .= ', ' . $city;
                    return $result;
                }
            }
        }

        return $result;
    }
}
