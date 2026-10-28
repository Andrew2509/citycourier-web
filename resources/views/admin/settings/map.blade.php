@extends('layouts.admin')

@section('title', 'Konfigurasi Peta & Rute')

@section('content')
<div class="space-y-5">
    {{-- Header Halaman --}}
    <div class="page-header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.settings.providers') }}" class="back-link" title="Kembali ke Daftar Provider">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            </a>
            <div>
                <h1 class="page-title">Konfigurasi Peta &amp; Routing</h1>
                <p class="page-subtitle">Kelola provider peta jalan untuk estimasi jarak instan, rute kurir, dan kalkulasi ongkos kirim.</p>
            </div>
        </div>

        <div class="page-actions">
            <span class="badge badge-indigo">
                Provider: {{ strtoupper($map['provider'] ?? 'OSRM') }}
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {{-- Form Kredensial Peta (2 Kolom) --}}
        <div class="lg:col-span-2 card">
            <div class="card-header">
                <div class="card-title">Pengaturan Provider Peta</div>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.settings.map.update') }}" method="POST" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="form-label" for="map_provider">Provider Routing &amp; Peta <span class="text-red-500">*</span></label>
                        <select name="map_provider" id="map_provider" class="form-select">
                            <option value="osrm" {{ old('map_provider', $map['provider'] ?? '') === 'osrm' ? 'selected' : '' }}>OpenStreetMap (OSRM - Gratis &amp; Tanpa Kuota)</option>
                            <option value="google" {{ old('map_provider', $map['provider'] ?? '') === 'google' ? 'selected' : '' }}>Google Maps Platform (Directions &amp; Distance Matrix)</option>
                            <option value="mapbox" {{ old('map_provider', $map['provider'] ?? '') === 'mapbox' ? 'selected' : '' }}>Mapbox Directions API</option>
                        </select>
                        <p class="form-help">OSRM direkomendasikan untuk pengiriman instan lokal dalam kota tanpa biaya API bulanan.</p>
                    </div>

                    <div>
                        <label class="form-label" for="map_api_key">API Key (Opsional)</label>
                        <input type="text"
                               name="map_api_key"
                               id="map_api_key"
                               value="{{ old('map_api_key', $map['api_key'] ?? '') }}"
                               placeholder="Masukkan API Key (Wajib jika menggunakan Google Maps / Mapbox)"
                               class="form-input font-mono text-xs">
                        <p class="form-help">Kosongkan jika menggunakan OSRM publik default.</p>
                    </div>

                    <div>
                        <label class="form-label" for="map_base_url">Routing Engine Base URL</label>
                        <input type="text"
                               name="map_base_url"
                               id="map_base_url"
                               value="{{ old('map_base_url', $map['base_url'] ?? '') }}"
                               placeholder="https://router.project-osrm.org"
                               class="form-input font-mono text-xs">
                        <p class="form-help">Alamat server OSRM self-hosted atau proxy routing internal jika tersedia.</p>
                    </div>

                    <div class="pt-3 border-t border-slate-200 flex items-center justify-end gap-2">
                        <a href="{{ route('admin.settings.providers') }}" class="btn btn-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary">
                            <span class="material-symbols-outlined">save</span>
                            Simpan Konfigurasi Peta
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Uji Coba Routing (1 Kolom) --}}
        <div class="space-y-5">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Uji Respon Routing</div>
                </div>
                <div class="card-body space-y-3">
                    <p class="text-xs text-slate-500">
                        Uji apakah server rute dapat menghitung koordinat dummy antara Surabaya dan Sidoarjo.
                    </p>
                    <form action="{{ route('admin.settings.map.test') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-secondary w-full">
                            <span class="material-symbols-outlined">directions</span>
                            Uji Coba Kalkulasi Rute
                        </button>
                    </form>
                </div>
            </div>

            <div class="card p-4 space-y-2 text-xs">
                <div class="font-semibold text-slate-900 border-b border-slate-100 pb-2">Informasi Rute Instan</div>
                <p class="text-slate-500 leading-relaxed">
                    Perhitungan jarak digunakan oleh modul pengiriman instan untuk menghitung estimasi menit kedatangan kurir dan tarif dasar per kilometer.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
