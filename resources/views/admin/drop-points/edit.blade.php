@extends('layouts.admin')

@section('title', 'Edit Drop Point')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-4">
        <a href="{{ route('admin.drop-points.index') }}" class="p-2 rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-all">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Edit Drop Point</h1>
            <p class="text-sm text-slate-400 mt-1">Perbarui data lokasi {{ $dropPoint->name }}</p>
        </div>
    </div>

    <!-- Form -->
    <div class="bg-white rounded-2xl border border-surface-border p-6 max-w-3xl">
        <form action="{{ route('admin.drop-points.update', $dropPoint) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
                <!-- Nama -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Nama Drop Point</label>
                    <input type="text" name="name"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all @error('name') border-error @enderror"
                           value="{{ old('name', $dropPoint->name) }}" placeholder="Contoh: Kantor Cabang Surabaya" required>
                    @error('name') <p class="text-xs text-error mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Alamat -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Alamat Lengkap</label>
                    <textarea name="address" rows="3"
                              class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all resize-none @error('address') border-error @enderror"
                              placeholder="Jl. Raya Utama No. 123..." required>{{ old('address', $dropPoint->address) }}</textarea>
                    @error('address') <p class="text-xs text-error mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Telepon -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Nomor Telepon</label>
                    <input type="text" name="phone"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all @error('phone') border-error @enderror"
                           value="{{ old('phone', $dropPoint->phone) }}" placeholder="021-xxxxxxx">
                    @error('phone') <p class="text-xs text-error mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Jam Operasional -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Jam Operasional</label>
                    <input type="text" name="schedule"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all @error('schedule') border-error @enderror"
                           value="{{ old('schedule', $dropPoint->schedule) }}" placeholder="08:00 - 21:00">
                    @error('schedule') <p class="text-xs text-error mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Rating -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Rating (0-5)</label>
                    <input type="number" name="rating"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all @error('rating') border-error @enderror"
                           value="{{ old('rating', $dropPoint->rating) }}" step="0.1" min="0" max="5">
                    @error('rating') <p class="text-xs text-error mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Status -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Status</label>
                    <select name="is_active" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                        <option value="1" {{ $dropPoint->is_active ? 'selected' : '' }}>Aktif</option>
                        <option value="0" {{ !$dropPoint->is_active ? 'selected' : '' }}>Non-aktif</option>
                    </select>
                </div>
            </div>

            <!-- Koordinat -->
            <div class="mb-6">
                <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">Koordinat Lokasi (Opsional)</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Latitude</label>
                        <input type="text" name="latitude"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all @error('latitude') border-error @enderror"
                               value="{{ old('latitude', $dropPoint->latitude) }}" placeholder="-6.xxxxxx">
                        @error('latitude') <p class="text-xs text-error mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Longitude</label>
                        <input type="text" name="longitude"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all @error('longitude') border-error @enderror"
                               value="{{ old('longitude', $dropPoint->longitude) }}" placeholder="106.xxxxxx">
                        @error('longitude') <p class="text-xs text-error mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between">
                <form action="{{ route('admin.drop-points.destroy', $dropPoint) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus drop point ini?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-error bg-error/10 hover:bg-error/20 transition-all">
                        Hapus
                    </button>
                </form>
                <button type="submit" class="px-6 py-2.5 rounded-xl text-sm font-semibold bg-gradient-to-r from-primary to-primary-light text-white hover:shadow-lg hover:shadow-primary/20 transition-all">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
