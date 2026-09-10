@extends('layouts.admin')

@section('title', 'Edit Drop Point')

@section('content')
<div class="flex flex-col gap-space-xl">
    {{-- Page Header --}}
    <div class="flex items-center gap-space-md">
        <a href="{{ route('admin.drop-points.index') }}" class="w-9 h-9 rounded-lg bg-surface-container-lowest border border-surface-container-high flex items-center justify-center text-on-surface-variant hover:bg-surface-container-high transition-colors shadow-sm">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
        </a>
        <div class="flex items-center gap-space-md">
            <div class="w-10 h-10 rounded-xl bg-primary-container/10 flex items-center justify-center">
                <span class="material-symbols-outlined text-[22px] text-primary">edit_location</span>
            </div>
            <div>
                <h1 class="font-headline-xl text-headline-xl text-on-surface font-bold tracking-tight">Edit Drop Point</h1>
                <p class="font-body-sm text-body-sm text-secondary mt-space-2xs">Perbarui data lokasi {{ $dropPoint->name }}</p>
            </div>
        </div>
    </div>

    {{-- Form --}}
    <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden max-w-3xl">
        <div class="px-space-xl py-space-md border-b border-surface-container-high">
            <h2 class="font-headline-sm text-headline-sm text-on-surface font-semibold flex items-center gap-space-xs">
                <span class="material-symbols-outlined text-primary">warehouse</span>
                Form Edit Drop Point
            </h2>
        </div>
        <div class="p-space-xl">
            <form action="{{ route('admin.drop-points.update', $dropPoint) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-space-lg mb-space-lg">
                    {{-- Nama --}}
                    <div class="md:col-span-2">
                        <label class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">Nama Drop Point</label>
                        <input type="text" name="name"
                               class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all @error('name') border-red-500 @enderror"
                               value="{{ old('name', $dropPoint->name) }}" placeholder="Contoh: Kantor Cabang Surabaya" required>
                        @error('name') <p class="text-xs text-red-500 mt-space-2xs">{{ $message }}</p> @enderror
                    </div>

                    {{-- Alamat --}}
                    <div class="md:col-span-2">
                        <label class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">Alamat Lengkap</label>
                        <textarea name="address" rows="3"
                                  class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all resize-none @error('address') border-red-500 @enderror"
                                  placeholder="Jl. Raya Utama No. 123..." required>{{ old('address', $dropPoint->address) }}</textarea>
                        @error('address') <p class="text-xs text-red-500 mt-space-2xs">{{ $message }}</p> @enderror
                    </div>

                    {{-- Telepon --}}
                    <div>
                        <label class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">Nomor Telepon</label>
                        <input type="text" name="phone"
                               class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all @error('phone') border-red-500 @enderror"
                               value="{{ old('phone', $dropPoint->phone) }}" placeholder="021-xxxxxxx">
                        @error('phone') <p class="text-xs text-red-500 mt-space-2xs">{{ $message }}</p> @enderror
                    </div>

                    {{-- Jam Operasional --}}
                    <div>
                        <label class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">Jam Operasional</label>
                        <input type="text" name="schedule"
                               class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all @error('schedule') border-red-500 @enderror"
                               value="{{ old('schedule', $dropPoint->schedule) }}" placeholder="08:00 - 21:00">
                        @error('schedule') <p class="text-xs text-red-500 mt-space-2xs">{{ $message }}</p> @enderror
                    </div>

                    {{-- Rating --}}
                    <div>
                        <label class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">Rating (0-5)</label>
                        <input type="number" name="rating"
                               class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all @error('rating') border-red-500 @enderror"
                               value="{{ old('rating', $dropPoint->rating) }}" step="0.1" min="0" max="5">
                        @error('rating') <p class="text-xs text-red-500 mt-space-2xs">{{ $message }}</p> @enderror
                    </div>

                    {{-- Status --}}
                    <div>
                        <label class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">Status</label>
                        <select name="is_active" class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all">
                            <option value="1" {{ $dropPoint->is_active ? 'selected' : '' }}>Aktif</option>
                            <option value="0" {{ !$dropPoint->is_active ? 'selected' : '' }}>Non-aktif</option>
                        </select>
                    </div>
                </div>

                {{-- Koordinat --}}
                <div class="mb-space-lg">
                    <h4 class="font-label-sm text-label-sm text-secondary uppercase tracking-wider mb-space-md">Koordinat Lokasi (Opsional)</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-space-lg">
                        <div>
                            <label class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">Latitude</label>
                            <input type="text" name="latitude"
                                   class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all @error('latitude') border-red-500 @enderror"
                                   value="{{ old('latitude', $dropPoint->latitude) }}" placeholder="-6.xxxxxx">
                            @error('latitude') <p class="text-xs text-red-500 mt-space-2xs">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">Longitude</label>
                            <input type="text" name="longitude"
                                   class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all @error('longitude') border-red-500 @enderror"
                                   value="{{ old('longitude', $dropPoint->longitude) }}" placeholder="106.xxxxxx">
                            @error('longitude') <p class="text-xs text-red-500 mt-space-2xs">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-space-md border-t border-surface-container-high">
                    <form action="{{ route('admin.drop-points.destroy', $dropPoint) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus drop point ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="px-space-md py-space-xs rounded-lg text-sm font-semibold text-red-600 bg-red-50 hover:bg-red-100 transition-all">
                            Hapus
                        </button>
                    </form>
                    <button type="submit" class="px-space-lg py-space-xs rounded-lg bg-primary-container hover:bg-primary text-on-primary font-label-md text-label-md font-bold shadow-sm transition-all flex items-center gap-space-xs">
                        <span class="material-symbols-outlined text-[18px]">save</span>
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
