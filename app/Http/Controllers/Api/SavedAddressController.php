<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SavedAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SavedAddressController extends Controller
{
    /**
     * Get all saved addresses for the authenticated user.
     * GET /api/addresses
     */
    public function index(Request $request)
    {
        $addresses = SavedAddress::where('user_id', $request->user()->id)
            ->orderByDesc('is_favorite')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $addresses,
        ]);
    }

    /**
     * Save a new address.
     * POST /api/addresses
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'  => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $address = SavedAddress::create([
            'user_id'          => $request->user()->id,
            'name'             => $request->name,
            'phone'            => $request->phone,
            'address'          => $request->address,
            'province_id'      => $request->province_id,
            'province_name'    => $request->province_name,
            'city_id'          => $request->city_id,
            'city_name'        => $request->city_name,
            'subdistrict_id'   => $request->subdistrict_id,
            'subdistrict_name' => $request->subdistrict_name,
            'is_favorite'      => $request->boolean('is_favorite'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Alamat berhasil disimpan.',
            'data'    => $address,
        ], 201);
    }

    /**
     * Update an existing address.
     * PUT /api/addresses/{id}
     */
    public function update(Request $request, SavedAddress $address)
    {
        if ($address->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Tidak diizinkan.'], 403);
        }

        $address->update([
            'name'             => $request->name ?? $address->name,
            'phone'            => $request->phone ?? $address->phone,
            'address'          => $request->address ?? $address->address,
            'province_id'      => $request->province_id ?? $address->province_id,
            'province_name'    => $request->province_name ?? $address->province_name,
            'city_id'          => $request->city_id ?? $address->city_id,
            'city_name'        => $request->city_name ?? $address->city_name,
            'subdistrict_id'   => $request->subdistrict_id ?? $address->subdistrict_id,
            'subdistrict_name' => $request->subdistrict_name ?? $address->subdistrict_name,
            'is_favorite'      => $request->has('is_favorite') ? $request->boolean('is_favorite') : $address->is_favorite,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Alamat berhasil diperbarui.',
            'data'    => $address->fresh(),
        ]);
    }

    /**
     * Delete an address.
     * DELETE /api/addresses/{id}
     */
    public function destroy(Request $request, SavedAddress $address)
    {
        if ($address->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Tidak diizinkan.'], 403);
        }

        $address->delete();

        return response()->json([
            'success' => true,
            'message' => 'Alamat berhasil dihapus.',
        ]);
    }

    /**
     * Toggle favorite status.
     * PATCH /api/addresses/{id}/favorite
     */
    public function toggleFavorite(Request $request, SavedAddress $address)
    {
        if ($address->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Tidak diizinkan.'], 403);
        }

        $address->update(['is_favorite' => !$address->is_favorite]);

        return response()->json([
            'success'     => true,
            'is_favorite' => $address->fresh()->is_favorite,
        ]);
    }
}
