<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MapApiController extends Controller
{
    protected $mapService;

    public function __construct(MapService $mapService)
    {
        $this->mapService = $mapService;
    }

    /**
     * Get routing path between origin and destination.
     */
    public function routing(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'origin' => 'required|string', // Format: "lng,lat"
            'destination' => 'required|string', // Format: "lng,lat"
            'vias' => 'nullable|array',
            'vias.*' => 'string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Format parameter salah.',
                'errors' => $validator->errors()
            ], 422);
        }

        $vias = $request->input('vias', []);
        $result = $this->mapService->getRoute($request->origin, $request->destination, $vias);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'data' => $result['data']
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'],
            'error' => $result['error'] ?? null
        ], 400);
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
            'message' => $result['message'],
            'error' => $result['error'] ?? null
        ], 400);
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
            'message' => $result['message'],
            'error' => $result['error'] ?? null
        ], 400);
    }

    /**
     * Get Style JSON and proxy external tile resources securely.
     */
    public function getStyle($name)
    {
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
}
