<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\DropPoint;
use Illuminate\Http\Request;

class DropPointController extends Controller
{
    public function index(Request $request)
    {
        $query = DropPoint::query();

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('pic_name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($wilayah = $request->get('wilayah')) {
            $query->where('city', $wilayah);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $dropPoints = $query->latest('rating')->paginate(10)->withQueryString();

        $total = DropPoint::count();
        $activeCount = DropPoint::where('status', 'active')->count();
        $cities = DropPoint::whereNotNull('city')->pluck('city')->unique()->values();
        $courierCount = Courier::where('is_verified', true)->count();
        $capacity = round((float) DropPoint::where('type', 'hub')->avg('capacity_pct') ?? 0);

        $mapPoint = DropPoint::where('status', 'active')->where('type', 'hub')->orderByDesc('rating')->first()
            ?? DropPoint::where('status', 'active')->first();

        return view('admin.drop-points.index', compact(
            'dropPoints',
            'total',
            'activeCount',
            'cities',
            'courierCount',
            'capacity',
            'mapPoint'
        ));
    }

    public function create()
    {
        return redirect()->route('admin.drop-points.index')->with('info', 'Gunakan tombol "Tambah Drop Point" pada halaman untuk membuka form.');
    }

    public function store(Request $request)
    {
        $validated = $this->validateDropPoint($request);
        $validated['is_active'] = $validated['status'] !== 'closed';

        DropPoint::create($validated);

        return redirect()->route('admin.drop-points.index')
            ->with('success', 'Drop Point berhasil ditambahkan.');
    }

    public function edit(DropPoint $dropPoint)
    {
        return redirect()->route('admin.drop-points.index')->with('info', 'Gunakan tombol "Edit" pada baris untuk memperbarui data.');
    }

    public function update(Request $request, DropPoint $dropPoint)
    {
        $validated = $this->validateDropPoint($request);
        $validated['is_active'] = $validated['status'] !== 'closed';

        $dropPoint->update($validated);

        return redirect()->route('admin.drop-points.index')
            ->with('success', 'Drop Point berhasil diperbarui.');
    }

    public function destroy(DropPoint $dropPoint)
    {
        $dropPoint->delete();

        return redirect()->route('admin.drop-points.index')
            ->with('success', 'Drop Point berhasil dihapus.');
    }

    public function updateRadius(Request $request, DropPoint $dropPoint)
    {
        $validated = $request->validate([
            'radius_m' => 'required|integer|min:10|max:2000',
        ]);

        $dropPoint->update($validated);

        return back()->with('success', "Radius geofence {$dropPoint->name} diperbarui menjadi {$dropPoint->radius_m} meter.");
    }

    public function toggleActive(DropPoint $dropPoint)
    {
        $active = ! $dropPoint->is_active;
        $dropPoint->update([
            'is_active' => $active,
            'status' => $active ? 'active' : 'closed',
        ]);

        return back()->with('success', 'Status Drop Point berhasil diperbarui.');
    }

    public function exportCsv()
    {
        $dropPoints = DropPoint::orderBy('name')->get();

        $fileName = 'drop-points-'.date('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($dropPoints) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Nama', 'Tipe', 'Alamat', 'Kota', 'Provinsi', 'Deskripsi', 'Landmark', 'Telepon', 'PIC',
                'Jam Kerja', 'Hari Buka', 'Rating', 'Radius Geofence (m)', 'Kapasitas (%)',
                'Latitude', 'Longitude', 'Status',
            ]);

            foreach ($dropPoints as $dp) {
                fputcsv($out, [
                    $dp->name,
                    strtoupper($dp->type),
                    $dp->address,
                    $dp->city,
                    $dp->province,
                    $dp->description,
                    $dp->landmark,
                    $dp->phone,
                    $dp->pic_name,
                    $dp->schedule,
                    $dp->open_days,
                    $dp->rating,
                    $dp->radius_m,
                    $dp->capacity_pct,
                    $dp->latitude,
                    $dp->longitude,
                    ucfirst($dp->status),
                ]);
            }

            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }

    protected function validateDropPoint(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:hub,agent,locker',
            'address' => 'required|string|max:500',
            'city' => 'nullable|string|max:255',
            'province' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'landmark' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:30',
            'pic_name' => 'nullable|string|max:255',
            'schedule' => 'nullable|string|max:255',
            'open_days' => 'nullable|string|max:255',
            'rating' => 'nullable|numeric|between:0,5',
            'radius_m' => 'nullable|integer|min:10|max:2000',
            'capacity_pct' => 'nullable|integer|between:0,100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'status' => 'required|in:active,renovation,closed',
        ]);
    }
}