@extends('layouts.admin')

@section('title', 'Edit Drop Point - ' . $dropPoint->name)

@section('content')
<div class="space-y-5 max-w-3xl">
    {{-- Header Halaman --}}
    <div class="page-header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.drop-points.index') }}" class="back-link" title="Kembali ke Daftar Drop Point">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            </a>
            <div>
                <h1 class="page-title">Edit Drop Point</h1>
                <p class="page-subtitle font-mono">Kode: {{ $dropPoint->code ?? ('DP-' . $dropPoint->id) }} &bull; {{ $dropPoint->name }}</p>
            </div>
        </div>

        <div class="page-actions">
            <form action="{{ route('admin.drop-points.destroy', $dropPoint) }}"
                  method="POST"
                  onsubmit="return confirm('Apakah Anda yakin ingin menghapus drop point {{ $dropPoint->name }}?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-secondary text-red-600 hover:bg-red-50 hover:border-red-200">
                    <span class="material-symbols-outlined text-[18px]">delete</span>
                    Hapus Drop Point
                </button>
            </form>
        </div>
    </div>

    {{-- Form Edit Drop Point --}}
    <div class="card">
        <div class="card-header">
            <div class="card-title">Perbarui Informasi Drop Point</div>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.drop-points.update', $dropPoint) }}" method="POST" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="form-label" for="name">
                        Nama Drop Point <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           name="name"
                           id="name"
                           value="{{ old('name', $dropPoint->name) }}"
                           required
                           class="form-input @error('name') form-invalid @enderror">
                    @error('name') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label" for="address">
                        Alamat Lengkap <span class="text-red-500">*</span>
                    </label>
                    <textarea name="address"
                              id="address"
                              rows="3"
                              required
                              class="form-textarea @error('address') form-invalid @enderror">{{ old('address', $dropPoint->address) }}</textarea>
                    @error('address') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label" for="phone">Nomor Telepon / Kontak</label>
                        <input type="text"
                               name="phone"
                               id="phone"
                               value="{{ old('phone', $dropPoint->phone) }}"
                               class="form-input font-mono @error('phone') form-invalid @enderror">
                        @error('phone') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label" for="schedule">Jam Operasional</label>
                        <input type="text"
                               name="schedule"
                               id="schedule"
                               value="{{ old('schedule', $dropPoint->schedule) }}"
                               class="form-input @error('schedule') form-invalid @enderror">
                        @error('schedule') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label" for="rating">Rating (0 - 5.0)</label>
                        <input type="number"
                               name="rating"
                               id="rating"
                               step="0.1"
                               min="0"
                               max="5"
                               value="{{ old('rating', $dropPoint->rating) }}"
                               class="form-input font-mono @error('rating') form-invalid @enderror">
                        @error('rating') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label" for="is_active">Status Operasional</label>
                        <select name="is_active" id="is_active" class="form-select">
                            <option value="1" {{ old('is_active', $dropPoint->is_active) ? 'selected' : '' }}>Aktif (Menerima Paket)</option>
                            <option value="0" {{ !old('is_active', $dropPoint->is_active) ? 'selected' : '' }}>Nonaktif (Tutup Sementara)</option>
                        </select>
                    </div>
                </div>

                {{-- Koordinat GPS --}}
                <div class="border-t border-slate-200 pt-3 space-y-3">
                    <div class="text-xs font-semibold text-slate-800">
                        Koordinat Lokasi Geografis (Opsional)
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label" for="latitude">Latitude</label>
                            <input type="text"
                                   name="latitude"
                                   id="latitude"
                                   value="{{ old('latitude', $dropPoint->latitude) }}"
                                   class="form-input font-mono text-xs @error('latitude') form-invalid @enderror">
                            @error('latitude') <p class="form-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="form-label" for="longitude">Longitude</label>
                            <input type="text"
                                   name="longitude"
                                   id="longitude"
                                   value="{{ old('longitude', $dropPoint->longitude) }}"
                                   class="form-input font-mono text-xs @error('longitude') form-invalid @enderror">
                            @error('longitude') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-200 flex items-center justify-end gap-2">
                    <a href="{{ route('admin.drop-points.index') }}" class="btn btn-secondary">
                        Batal
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <span class="material-symbols-outlined">save</span>
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
