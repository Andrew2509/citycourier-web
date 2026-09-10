@extends('layouts.admin')

@section('title', 'Konfigurasi Peta')
@section('page-title', 'Konfigurasi Peta')

@section('content')
<div class="flex flex-col gap-space-xl">
    {{-- Page Header --}}
    <div class="flex items-center gap-space-md">
        <div class="w-12 h-12 rounded-xl bg-primary-container text-on-primary flex items-center justify-center shadow-sm shrink-0">
            <span class="material-symbols-outlined text-[22px]">map</span>
        </div>
        <div>
            <h1 class="font-headline-xl text-headline-xl text-on-surface tracking-tight font-bold">Pengaturan Map Server</h1>
            <p class="font-body-sm text-body-sm text-secondary mt-space-2xs">Kelola konfigurasi peta untuk proxy ekosistem Flutter</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-space-xl">
        {{-- Main Form --}}
        <div class="lg:col-span-2">
            <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
                <div class="px-space-xl py-space-md border-b border-surface-container-high">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface font-semibold flex items-center gap-space-xs">
                        <span class="material-symbols-outlined text-primary">settings</span>
                        Konfigurasi API Peta
                    </h3>
                </div>
                <div class="p-space-xl">
                    <form action="{{ route('admin.settings.map.update') }}" method="POST">
                        @csrf
                        
                        {{-- Provider --}}
                        <div class="mb-space-lg">
                            <label class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">Penyedia Peta (Map Provider)</label>
                            <select name="map_provider" id="map_provider" class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all">
                                <option value="osrm" {{ old('map_provider', $settings['provider'] ?? 'osrm') == 'osrm' ? 'selected' : '' }}>OSRM / OpenStreetMap (Recommended)</option>
                                <option value="maplibre" {{ old('map_provider', $settings['provider'] ?? 'osrm') == 'maplibre' ? 'selected' : '' }}>Maplibre GL (Open Source)</option>
                                <option value="mapbox" {{ old('map_provider', $settings['provider'] ?? 'osrm') == 'mapbox' ? 'selected' : '' }}>Mapbox</option>
                                <option value="google" {{ old('map_provider', $settings['provider'] ?? 'osrm') == 'google' ? 'selected' : '' }}>Google Maps API</option>
                            </select>
                            @error('map_provider') <p class="text-xs text-red-500 mt-space-2xs">{{ $message }}</p> @enderror
                        </div>

                        {{-- Base URL --}}
                        <div class="mb-space-lg">
                            <label class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">API Base URL</label>
                            <input type="url" name="map_base_url" id="map_base_url" value="{{ old('map_base_url', $settings['base_url']) }}" class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all" placeholder="https://router.project-osrm.org">
                            @error('map_base_url') <p class="text-xs text-red-500 mt-space-2xs">{{ $message }}</p> @enderror
                        </div>

                        {{-- API Key --}}
                        <div class="mb-space-lg">
                            <label class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">Access Token / API Key</label>
                            <input type="password" name="map_api_key" id="map_api_key" value="{{ old('map_api_key', $settings['api_key']) }}" class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all" placeholder="Kosongkan untuk OSRM publik">
                            @error('map_api_key') <p class="text-xs text-red-500 mt-space-2xs">{{ $message }}</p> @enderror
                            <p class="font-label-sm text-label-sm text-secondary mt-space-sm" id="api-key-hint">Semua request dari Flutter akan diproyeksi melalui Laravel untuk melindungi token ini.</p>
                        </div>

                        {{-- Actions --}}
                        <div class="flex items-center justify-end gap-space-md pt-space-md border-t border-surface-container-high">
                            <button type="button" id="btn-test-map" class="px-space-md py-space-xs rounded-lg border border-surface-container-high text-on-surface-variant hover:bg-surface-container-high transition-colors text-sm font-medium flex items-center gap-space-xs">
                                <span class="material-symbols-outlined text-[16px]">wifi_tethering</span>
                                Cek Koneksi
                            </button>
                            <button type="submit" class="px-space-lg py-space-xs rounded-lg bg-primary-container hover:bg-primary text-on-primary transition-all text-sm font-semibold flex items-center gap-space-xs shadow-sm">
                                <span class="material-symbols-outlined text-[16px]">save</span>
                                Simpan Perubahan
                            </button>
                        </div>
                    </form>

                    {{-- Test Result --}}
                    <div id="test-result" class="mt-space-lg hidden">
                        <div id="test-alert" class="p-space-md rounded-lg">
                            <p class="font-semibold text-sm" id="test-title"></p>
                            <p class="text-sm mt-space-2xs" id="test-message"></p>
                            <div id="test-data" class="mt-space-sm p-space-sm bg-surface-container-lowest rounded-lg border border-surface-container-high text-xs hidden"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Info Panel --}}
        <div class="flex flex-col gap-space-xl">
            <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
                <div class="px-space-xl py-space-md border-b border-surface-container-high">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface font-semibold flex items-center gap-space-xs">
                        <span class="material-symbols-outlined text-primary">architecture</span>
                        Arsitektur Proxy
                    </h3>
                </div>
                <div class="p-space-xl">
                    <p class="font-body-sm text-body-sm text-on-surface mb-space-md">Laravel bertindak sebagai <strong>Proxy & Security Guard</strong>. Token peta aman di server.</p>
                    <div class="flex flex-col gap-space-sm">
                        <p class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Endpoint yang di-Proxy:</p>
                        <div class="flex flex-col gap-space-sm">
                            <div class="flex items-center gap-space-xs p-space-sm bg-surface rounded-lg">
                                <span class="px-space-xs py-space-2xs bg-blue-100 text-blue-700 rounded text-xs font-bold">POST</span>
                                <code class="font-data-mono text-xs text-on-surface-variant">/api/shipping/map/routing</code>
                            </div>
                            <div class="flex items-center gap-space-xs p-space-sm bg-surface rounded-lg">
                                <span class="px-space-xs py-space-2xs bg-emerald-100 text-emerald-700 rounded text-xs font-bold">GET</span>
                                <code class="font-data-mono text-xs text-on-surface-variant">/api/shipping/map/autocomplete</code>
                            </div>
                            <div class="flex items-center gap-space-xs p-space-sm bg-surface rounded-lg">
                                <span class="px-space-xs py-space-2xs bg-blue-100 text-blue-700 rounded text-xs font-bold">POST</span>
                                <code class="font-data-mono text-xs text-on-surface-variant">/api/shipping/map/matrix</code>
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
            testAlert.className = 'p-space-md rounded-lg ' + (data.success ? 'bg-emerald-50 border border-emerald-200' : 'bg-red-50 border border-red-200');
            document.getElementById('test-title').innerText = data.success ? '✓ Koneksi Berhasil' : '✗ Koneksi Gagal';
            document.getElementById('test-title').className = 'font-semibold text-sm ' + (data.success ? 'text-emerald-700' : 'text-red-700');
            document.getElementById('test-message').innerText = data.message;
        })
        .catch(() => {
            document.getElementById('test-result').classList.remove('hidden');
            document.getElementById('test-alert').className = 'p-space-md rounded-lg bg-red-50 border border-red-200';
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
