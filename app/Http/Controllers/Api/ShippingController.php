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

        if (isset($result['rajaongkir']['status']['code']) && $result['rajaongkir']['status']['code'] == 200) {
            return response()->json([
                'success' => true,
                'data' => $result['rajaongkir']['results']
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['rajaongkir']['status']['description'] ?? 'Gagal mengambil data provinsi.'
        ], 400);
    }

    /**
     * List all cities or cities in a province.
     */
    public function cities(Request $request)
    {
        $result = $this->rajaOngkir->getCities($request->province_id);

        if (isset($result['rajaongkir']['status']['code']) && $result['rajaongkir']['status']['code'] == 200) {
            return response()->json([
                'success' => true,
                'data' => $result['rajaongkir']['results']
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['rajaongkir']['status']['description'] ?? 'Gagal mengambil data kota.'
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
            'courier' => 'required|string'
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

        if (isset($result['rajaongkir']['status']['code']) && $result['rajaongkir']['status']['code'] == 200) {
            return response()->json([
                'success' => true,
                'data' => $result['rajaongkir']['results']
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['rajaongkir']['status']['description'] ?? 'Gagal menghitung ongkos kirim.'
        ], 400);
    }
}
