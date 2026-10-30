@extends('layouts.admin')

@section('title', 'Provider RajaOngkir')

@section('content')
<div class="space-y-5">
    {{-- Header Halaman --}}
    <div class="page-header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.settings.providers') }}" class="back-link" title="Kembali ke Daftar Provider">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            </a>
            <div>
                <h1 class="page-title">Provider RajaOngkir</h1>
                <p class="page-subtitle">Kelola konfigurasi API referensi tarif ekspedisi nasional dan basis data kecamatan.</p>
            </div>
        </div>

        <div class="page-actions">
            <span class="badge badge-amber">
                Tipe: {{ strtoupper($rajaongkir['account_type'] ?? 'STARTER') }}
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {{-- Form Kredensial RajaOngkir (2 Kolom) --}}
        <div class="lg:col-span-2 card">
            <div class="card-header">
                <div class="card-title">Kredensial Akun RajaOngkir</div>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.settings.rajaongkir.update') }}" method="POST" class="space-y-4">
                    @csrf

                    <div>
                        <label class="form-label" for="rajaongkir_api_key">API Key RajaOngkir <span class="text-red-500">*</span></label>
                        <input type="text"
                               name="rajaongkir_api_key"
                               id="rajaongkir_api_key"
                               value="{{ old('rajaongkir_api_key', $rajaongkir['api_key'] ?? '') }}"
                               required
                               placeholder="Masukkan API Key RajaOngkir Anda..."
                               class="form-input font-mono text-xs">
                        <p class="form-help">Dapatkan API Key dari dashboard akun RajaOngkir Anda.</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label" for="rajaongkir_account_type">Tipe Lisensi Akun <span class="text-red-500">*</span></label>
                            <select name="rajaongkir_account_type" id="rajaongkir_account_type" class="form-select">
                                <option value="starter" {{ old('rajaongkir_account_type', $rajaongkir['account_type'] ?? '') === 'starter' ? 'selected' : '' }}>Starter (Gratis - Kota saja)</option>
                                <option value="basic" {{ old('rajaongkir_account_type', $rajaongkir['account_type'] ?? '') === 'basic' ? 'selected' : '' }}>Basic (Internasional &amp; Berat)</option>
                                <option value="pro" {{ old('rajaongkir_account_type', $rajaongkir['account_type'] ?? '') === 'pro' ? 'selected' : '' }}>Pro (Mendukung Tingkat Kecamatan / Sub-district)</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label" for="rajaongkir_provider">Driver Provider</label>
                            <select name="rajaongkir_provider" id="rajaongkir_provider" class="form-select">
                                <option value="rajaongkir" {{ old('rajaongkir_provider', $rajaongkir['provider'] ?? '') === 'rajaongkir' ? 'selected' : '' }}>RajaOngkir Official</option>
                                <option value="mock" {{ old('rajaongkir_provider', $rajaongkir['provider'] ?? '') === 'mock' ? 'selected' : '' }}>Mock (Simulasi Lokal)</option>
                            </select>
                        </div>
                    </div>

                    <div class="pt-2">
                        <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-medium text-slate-700">
                            <input type="checkbox"
                                   name="rajaongkir_sandbox"
                                   value="1"
                                   {{ !empty($rajaongkir_sandbox) ? 'checked' : '' }}
                                   class="form-check">
                            <span>Gunakan mode Sandbox untuk pengujian tarif</span>
                        </label>
                    </div>

                    <div class="pt-3 border-t border-slate-200 flex items-center justify-end gap-2">
                        <a href="{{ route('admin.settings.providers') }}" class="btn btn-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary">
                            <span class="material-symbols-outlined">save</span>
                            Simpan Kredensial RajaOngkir
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Uji Coba RajaOngkir (1 Kolom) --}}
        <div class="space-y-5">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Uji Coba Cek Ongkir</div>
                </div>
                <div class="card-body space-y-3">
                    <p class="text-xs text-slate-500">
                        Cek koneksi API RajaOngkir untuk memverifikasi kuota dan validitas token akun.
                    </p>
                    <form action="{{ route('admin.settings.rajaongkir.test') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-secondary w-full">
                            <span class="material-symbols-outlined">local_shipping</span>
                            Tes Koneksi API Ongkir
                        </button>
                    </form>
                </div>
            </div>

            <div class="card p-4 space-y-2 text-xs">
                <div class="font-semibold text-slate-900 border-b border-slate-100 pb-2">Catatan Akun Pro</div>
                <p class="text-slate-500 leading-relaxed">
                    Pengiriman instan City Courier menggunakan perhitungan jarak koordinat (per-km). RajaOngkir Pro digunakan sebagai pembanding saat pengiriman antar-kota atau ekspedisi rekanan.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
