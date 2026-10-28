@extends('layouts.admin')

@section('title', 'Tambah Drop Point')

@section('content')
<div class="space-y-5 max-w-3xl">
    {{-- Header Halaman --}}
    <div class="page-header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.drop-points.index') }}" class="back-link" title="Kembali ke Daftar Drop Point">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            </a>
            <div>
                <h1 class="page-title">Tambah Drop Point Baru</h1>
                <p class="page-subtitle">Daftarkan lokasi kantor cabang, gudang hub, atau gerai drop point baru.</p>
            </div>
        </div>
    </div>

    {{-- Form Tambah Drop Point --}}
    <div class="card">
        <div class="card-header">
            <div class="card-title">Form Data Lokasi Drop Point</div>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.drop-points.store') }}" method="POST" class="space-y-4">
                @csrf

                <div>
                    <label class="form-label" for="name">
                        Nama Drop Point <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           name="name"
                           id="name"
                           value="{{ old('name') }}"
                           placeholder="Contoh: Drop Point Waru Sidoarjo"
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
                              placeholder="Jl. Raya Brigjend Katamso No. 12, Waru..."
                              required
                              class="form-textarea @error('address') form-invalid @enderror">{{ old('address') }}</textarea>
                    @error('address') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label" for="phone">Nomor Telepon / WhatsApp</label>
                        <input type="text"
                               name="phone"
                               id="phone"
                               value="{{ old('phone') }}"
                               placeholder="Contoh: 08123456789 atau 031-866xxxx"
                               class="form-input font-mono @error('phone') form-invalid @enderror">
                        @error('phone') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label" for="schedule">Jam Operasional</label>
                        <input type="text"
                               name="schedule"
                               id="schedule"
                               value="{{ old('schedule', '08:00 - 21:00') }}"
                               placeholder="Contoh: 08:00 - 21:00 WIB"
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
                               value="{{ old('rating', '4.8') }}"
                               class="form-input font-mono @error('rating') form-invalid @enderror">
                        @error('rating') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label" for="is_active">Status Operasional</label>
                        <select name="is_active" id="is_active" class="form-select">
                            <option value="1" {{ old('is_active', '1') == '1' ? 'selected' : '' }}>Aktif (Menerima Paket)</option>
                            <option value="0" {{ old('is_active') === '0' ? 'selected' : '' }}>Nonaktif (Tutup Sementara)</option>
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
                                   value="{{ old('latitude') }}"
                                   placeholder="Contoh: -7.3498032"
                                   class="form-input font-mono text-xs @error('latitude') form-invalid @enderror">
                            @error('latitude') <p class="form-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="form-label" for="longitude">Longitude</label>
                            <input type="text"
                                   name="longitude"
                                   id="longitude"
                                   value="{{ old('longitude') }}"
                                   placeholder="Contoh: 112.7498118"
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
                        Simpan Drop Point
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
