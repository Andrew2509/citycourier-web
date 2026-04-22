<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RajaOngkirService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ShippingController extends Controller
{
    protected $rajaOngkir;

    public function __construct(RajaOngkirService $rajaOngkir)
    {
        $this->rajaOngkir = $rajaOngkir;
    }

    /**
     * List all provinces.
     */
    public function provinces()
    {
        $result = $this->rajaOngkir->getProvinces();

        // Standard RajaOngkir
        if (isset($result['rajaongkir']['status']['code']) && $result['rajaongkir']['status']['code'] == 200) {
            return response()->json([
                'success' => true,
                'data' => $result['rajaongkir']['results']
            ]);
        }

        // Komerce
        $isKomerceSuccess = (isset($result['status']) && $result['status'] == true) || 
                           (isset($result['meta']['status']) && $result['meta']['status'] == 'success');

        if ($isKomerceSuccess) {
            // Normalize Komerce province keys
            $normalized = array_map(function($item) {
                return [
                    'province_id' => (string)($item['province_id'] ?? ($item['id'] ?? '')),
                    'province' => $item['province_name'] ?? ($item['name'] ?? ($item['province'] ?? '')),
                ];
            }, $result['data']);

            return response()->json([
                'success' => true,
                'data' => $normalized
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['rajaongkir']['status']['description'] ?? ($result['message'] ?? 'Gagal mengambil data provinsi.')
        ], 400);
    }

    /**
     * List all cities in a province.
     */
    public function cities(Request $request)
    {
        $request->validate([
            'province_id' => 'required'
        ]);

        $result = $this->rajaOngkir->getCities($request->province_id);

        // Standard RajaOngkir
        if (isset($result['rajaongkir']['status']['code']) && $result['rajaongkir']['status']['code'] == 200) {
            return response()->json([
                'success' => true,
                'data' => $result['rajaongkir']['results']
            ]);
        }

        // Komerce
        $isKomerceSuccess = (isset($result['status']) && $result['status'] == true) || 
                           (isset($result['meta']['status']) && $result['meta']['status'] == 'success');

        if ($isKomerceSuccess) {
            // Normalize Komerce city keys
            $normalized = array_map(function($item) {
                return [
                    'city_id' => (string)($item['city_id'] ?? ($item['id'] ?? '')),
                    'city_name' => $item['city_name'] ?? ($item['name'] ?? ''),
                    'province_id' => (string)($item['province_id'] ?? ''),
                    'province' => $item['province_name'] ?? '',
                    'type' => $item['type'] ?? '',
                    'postal_code' => $item['postal_code'] ?? '',
                ];
            }, $result['data']);

            return response()->json([
                'success' => true,
                'data' => $normalized
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['rajaongkir']['status']['description'] ?? ($result['message'] ?? 'Gagal mengambil data kota.')
        ], 400);
    }

    /**
     * Calculate shipping cost.
     */
    public function calculateCost(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'origin' => 'required',
            'destination' => 'required',
            'weight' => 'required|numeric',
            'courier' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->rajaOngkir->calculateCost(
            $request->origin,
            $request->destination,
            $request->weight,
            $request->courier
        );

        // Standard RajaOngkir
        if (isset($result['rajaongkir']['status']['code']) && $result['rajaongkir']['status']['code'] == 200) {
            return response()->json([
                'success' => true,
                'data' => $result['rajaongkir']['results']
            ]);
        }

        // Komerce
        $isKomerceSuccess = (isset($result['status']) && $result['status'] == true) || 
                           (isset($result['meta']['status']) && $result['meta']['status'] == 'success');

        if ($isKomerceSuccess) {
            // Normalize Komerce cost structure
            // Komerce returns a flat list of services, we need to group them by courier or just wrap them
            $normalized = [];
            foreach ($result['data'] as $item) {
                $courierCode = $item['courier_code'] ?? 'unknown';
                if (!isset($normalized[$courierCode])) {
                    $normalized[$courierCode] = [
                        'code' => $courierCode,
                        'name' => $item['courier_name'] ?? strtoupper($courierCode),
                        'costs' => []
                    ];
                }
                
                $normalized[$courierCode]['costs'][] = [
                    'service' => $item['service'],
                    'description' => $item['service'],
                    'cost' => [
                        [
                            'value' => $item['cost'],
                            'etd' => $item['etd'],
                            'note' => ''
                        ]
                    ]
                ];
            }

            return response()->json([
                'success' => true,
                'data' => array_values($normalized)
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['rajaongkir']['status']['description'] ?? ($result['message'] ?? 'Gagal menghitung ongkos kirim.')
        ], 400);
    }

    /**
     * List all districts in a city.
     */
    public function districts(Request $request)
    {
        $request->validate([
            'city_id' => 'required'
        ]);

        $result = $this->rajaOngkir->getDistricts($request->city_id);

        // Standard RajaOngkir
        if (isset($result['rajaongkir']['status']['code']) && $result['rajaongkir']['status']['code'] == 200) {
            return response()->json([
                'success' => true,
                'data' => $result['rajaongkir']['results']
            ]);
        }

        // Komerce
        $isKomerceSuccess = (isset($result['status']) && $result['status'] == true) || 
                           (isset($result['meta']['status']) && $result['meta']['status'] == 'success');

        if ($isKomerceSuccess) {
            // Normalize Komerce district keys
            $normalized = array_map(function($item) {
                return [
                    'subdistrict_id' => (string)($item['kecamatan_id'] ?? ($item['district_id'] ?? ($item['id'] ?? ''))),
                    'subdistrict_name' => $item['kecamatan_name'] ?? ($item['district_name'] ?? ($item['name'] ?? '')),
                    'city_id' => (string)($item['city_id'] ?? ''),
                ];
            }, $result['data']);

            return response()->json([
                'success' => true,
                'data' => $normalized
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['rajaongkir']['status']['description'] ?? ($result['message'] ?? 'Gagal mengambil data kecamatan.')
        ], 400);
    }

    /**
     * List all sub-districts (kelurahan/desa) in a district.
     */
    public function subdistricts(Request $request)
    {
        $request->validate([
            'district_id' => 'required'
        ]);

        $result = $this->rajaOngkir->getSubdistricts($request->district_id);

        // Standard RajaOngkir (Subdistrict might not be available in Basic/Starter)
        if (isset($result['rajaongkir']['status']['code']) && $result['rajaongkir']['status']['code'] == 200) {
            return response()->json([
                'success' => true,
                'data' => $result['rajaongkir']['results']
            ]);
        }

        // Komerce
        $isKomerceSuccess = (isset($result['status']) && $result['status'] == true) || 
                           (isset($result['meta']['status']) && $result['meta']['status'] == 'success');

        if ($isKomerceSuccess) {
            // Normalize Komerce subdistrict keys
            $normalized = array_map(function($item) {
                return [
                    'id' => (string)($item['village_id'] ?? ($item['id'] ?? '')),
                    'name' => $item['village_name'] ?? ($item['name'] ?? ''),
                    'kecamatan_id' => (string)($item['kecamatan_id'] ?? ($item['district_id'] ?? '')),
                    'zip_code' => $item['zip_code'] ?? '',
                ];
            }, $result['data']);

            return response()->json([
                'success' => true,
                'data' => $normalized
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['rajaongkir']['status']['description'] ?? ($result['message'] ?? 'Gagal mengambil data kelurahan.')
        ], 400);
    }
}
