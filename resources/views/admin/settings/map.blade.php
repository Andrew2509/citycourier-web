@extends('layouts.admin')

@section('title', 'Konfigurasi Peta')
@section('page-title', 'Konfigurasi Peta')

@section('content')
<div class="flex flex-col gap-6">
    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary to-primary-light flex items-center justify-center shadow-lg shadow-primary/20">
                <span class="material-symbols-outlined text-white text-xl">map</span>
            </div>
            Pengaturan Map Server
        </h1>
        <p class="text-sm text-slate-500 mt-1">Kelola konfigurasi peta untuk proxy ekosistem Flutter</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main Form --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100">
                    <h3 class="text-lg font-semibold text-slate-800 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">settings</span>
                        Konfigurasi API Peta
                    </h3>
                </div>
                <div class="p-5">
                    <form action="{{ route('admin.settings.map.update') }}" method="POST">
                        @csrf
                        
                        {{-- Provider --}}
                        <div class="mb-5">
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Penyedia Peta (Map Provider)</label>
                            <select name="map_provider" id="map_provider" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all">
                                <option value="osrm" {{ old('map_provider', $settings['provider'] ?? 'osrm') == 'osrm' ? 'selected' : '' }}>OSRM / OpenStreetMap (Recommended)</option>
                                <option value="maplibre" {{ old('map_provider', $settings['provider'] ?? 'osrm') == 'maplibre' ? 'selected' : '' }}>Maplibre GL (Open Source)</option>
                                <option value="mapbox" {{ old('map_provider', $settings['provider'] ?? 'osrm') == 'mapbox' ? 'selected' : '' }}>Mapbox</option>
                                <option value="google" {{ old('map_provider', $settings['provider'] ?? 'osrm') == 'google' ? 'selected' : '' }}>Google Maps API</option>
                            </select>
                            @error('map_provider') <p class="text-sm text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Base URL --}}
                        <div class="mb-5">
                            <label class="block text-sm font-semibold text-slate-700 mb-2">API Base URL</label>
                            <input type="url" name="map_base_url" id="map_base_url" value="{{ old('map_base_url', $settings['base_url']) }}" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all" placeholder="https://router.project-osrm.org">
                            @error('map_base_url') <p class="text-sm text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- API Key --}}
                        <div class="mb-5">
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Access Token / API Key</label>
                            <input type="password" name="map_api_key" id="map_api_key" value="{{ old('map_api_key', $settings['api_key']) }}" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all" placeholder="Kosongkan untuk OSRM publik">
                            @error('map_api_key') <p class="text-sm text-red-500 mt-1">{{ $message }}</p> @enderror
                            <p class="text-xs text-slate-400 mt-2" id="api-key-hint">Semua request dari Flutter akan diproyeksi melalui Laravel untuk melindungi token ini.</p>
                        </div>

                        {{-- Actions --}}
                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                            <button type="button" id="btn-test-map" class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors text-sm font-medium flex items-center gap-2">
                                <span class="material-symbols-outlined text-sm">wifi_tethering</span>
                                Cek Koneksi
                            </button>
                            <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-primary to-primary-light text-white hover:shadow-lg hover:shadow-primary/30 transition-all text-sm font-semibold flex items-center gap-2">
                                <span class="material-symbols-outlined text-sm">save</span>
                                Simpan Perubahan
                            </button>
                        </div>
                    </form>

                    {{-- Test Result --}}
                    <div id="test-result" class="mt-5 hidden">
                        <div id="test-alert" class="p-4 rounded-xl">
                            <p class="font-semibold text-sm" id="test-title"></p>
                            <p class="text-sm mt-1" id="test-message"></p>
                            <div id="test-data" class="mt-3 p-3 bg-white rounded-lg border border-slate-200 text-xs hidden"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Info Panel --}}
        <div class="space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100">
                    <h3 class="text-lg font-semibold text-slate-800 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">architecture</span>
                        Arsitektur Proxy
                    </h3>
                </div>
                <div class="p-5">
                    <p class="text-sm text-slate-600 mb-4">Laravel bertindak sebagai <strong>Proxy & Security Guard</strong>. Token peta aman di server.</p>
                    <div class="space-y-2">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Endpoint yang di-Proxy:</p>
                        <div class="space-y-2">
                            <div class="flex items-center gap-2 p-2 bg-slate-50 rounded-lg">
                                <span class="px-1.5 py-0.5 bg-blue-100 text-blue-700 rounded text-xs font-bold">POST</span>
                                <code class="text-xs text-slate-600">/api/shipping/map/routing</code>
                            </div>
                            <div class="flex items-center gap-2 p-2 bg-slate-50 rounded-lg">
                                <span class="px-1.5 py-0.5 bg-green-100 text-green-700 rounded text-xs font-bold">GET</span>
                                <code class="text-xs text-slate-600">/api/shipping/map/autocomplete</code>
                            </div>
                            <div class="flex items-center gap-2 p-2 bg-slate-50 rounded-lg">
                                <span class="px-1.5 py-0.5 bg-blue-100 text-blue-700 rounded text-xs font-bold">POST</span>
                                <code class="text-xs text-slate-600">/api/shipping/map/matrix</code>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const providerSelect = document.getElementById('map_provider');
    const baseUrlInput = document.getElementById('map_base_url');
    const apiKeyInput = document.getElementById('map_api_key');
    const apiKeyHint = document.getElementById('api-key-hint');
    const btnTest = document.getElementById('btn-test-map');

    const PROVIDER_DEFAULTS = {
        osrm: 'https://router.project-osrm.org',
        mapbox: 'https://api.mapbox.com',
        maplibre: 'https://demotiles.maplibre.org',
        google: 'https://maps.googleapis.com',
    };

    providerSelect.addEventListener('change', function() {
        const preset = PROVIDER_DEFAULTS[this.value];
        if (preset) baseUrlInput.value = preset;
        const isOsrm = this.value === 'osrm';
        apiKeyInput.placeholder = isOsrm ? 'Kosongkan untuk OSRM publik' : 'Masukkan Access Token';
        apiKeyHint.innerText = isOsrm ? 'OSRM publik tidak memerlukan token.' : 'Token akan diproyeksi melalui Laravel.';
    });

    btnTest.addEventListener('click', function() {
        btnTest.disabled = true;
        btnTest.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">progress_activity</span> Menghubungkan...';
        
        fetch("{{ route('admin.settings.map.test') }}", {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        })
        .then(r => r.json())
        .then(data => {
            const testResult = document.getElementById('test-result');
            const testAlert = document.getElementById('test-alert');
            testResult.classList.remove('hidden');
            testAlert.className = 'p-4 rounded-xl ' + (data.success ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200');
            document.getElementById('test-title').innerText = data.success ? '✓ Koneksi Berhasil' : '✗ Koneksi Gagal';
            document.getElementById('test-title').className = 'font-semibold text-sm ' + (data.success ? 'text-green-700' : 'text-red-700');
            document.getElementById('test-message').innerText = data.message;
        })
        .catch(() => {
            document.getElementById('test-result').classList.remove('hidden');
            document.getElementById('test-alert').className = 'p-4 rounded-xl bg-red-50 border border-red-200';
            document.getElementById('test-title').innerText = 'Error';
            document.getElementById('test-message').innerText = 'Terjadi kesalahan sistem.';
        })
        .finally(() => {
            btnTest.disabled = false;
            btnTest.innerHTML = '<span class="material-symbols-outlined text-sm">wifi_tethering</span> Cek Koneksi';
        });
    });
});
</script>
@endsection
