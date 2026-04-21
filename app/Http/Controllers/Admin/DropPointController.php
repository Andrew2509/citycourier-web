<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DropPoint;
use Illuminate\Http\Request;

class DropPointController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $dropPoints = DropPoint::latest()->paginate(10);
        return view('admin.drop-points.index', compact('dropPoints'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.drop-points.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string',
            'phone' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'is_active' => 'boolean',
        ]);

        DropPoint::create($validated);

        return redirect()->route('admin.drop-points.index')
            ->with('success', 'Drop Point berhasil ditambahkan.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DropPoint $dropPoint)
    {
        return view('admin.drop-points.edit', compact('dropPoint'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DropPoint $dropPoint)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string',
            'phone' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'is_active' => 'boolean',
        ]);

        $dropPoint->update($validated);

        return redirect()->route('admin.drop-points.index')
            ->with('success', 'Drop Point berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DropPoint $dropPoint)
    {
        $dropPoint->delete();

        return redirect()->route('admin.drop-points.index')
            ->with('success', 'Drop Point berhasil dihapus.');
    }

    /**
     * Toggle the active status.
     */
    public function toggleActive(DropPoint $dropPoint)
    {
        $dropPoint->update(['is_active' => !$dropPoint->is_active]);
        return back()->with('success', 'Status Drop Point berhasil diperbarui.');
    }
}
