@extends('layouts.admin')

@section('title', 'Map Server Settings')
@section('page-title', 'Pengaturan Map Server')
@section('page-subtitle', 'Kelola konfigurasi API Mapbox, Maplibre, atau Google Maps untuk proxy ekosistem Flutter')

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card bg-white shadow-sm rounded-3">
            <div class="card-header border-0 bg-transparent pt-4 px-4">
                <h3 class="card-title fw-bold text-dark fs-5">Konfigurasi API Peta</h3>
            </div>
            <div class="card-body px-4 pb-4">
                <form action="{{ route('admin.settings.map.update') }}" method="POST">
                    @csrf
                    
                    <div class="form-group mb-4">
                        <label for="map_provider" class="form-label fw-semibold text-muted small uppercase">Penyedia Peta (Map Provider)</label>
                        <select name="map_provider" id="map_provider" class="form-control rounded-3 border-light-subtle @error('map_provider') is-invalid @enderror">
                            <option value="maplibre" {{ old('map_provider', $settings['provider'] ?? 'maplibre') == 'maplibre' ? 'selected' : '' }}>Maplibre GL (Recommended - Open Source)</option>
                            <option value="mapbox" {{ old('map_provider', $settings['provider'] ?? 'maplibre') == 'mapbox' ? 'selected' : '' }}>Mapbox</option>
                            <option value="google" {{ old('map_provider', $settings['provider'] ?? 'maplibre') == 'google' ? 'selected' : '' }}>Google Maps API</option>
                        </select>
                        @error('map_provider')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group mb-4">
                        <label for="map_base_url" class="form-label fw-semibold text-muted small uppercase">API Base URL</label>
                        <input type="url" 
                               name="map_base_url" 
                               id="map_base_url" 
                               class="form-control rounded-3 border-light-subtle @error('map_base_url') is-invalid @enderror" 
                               value="{{ old('map_base_url', $settings['base_url']) }}" 
                               placeholder="Contoh: https://api.mapbox.com">
                        @error('map_base_url')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group mb-4">
                        <label for="map_api_key" class="form-label fw-semibold text-muted small uppercase">Access Token / API Key</label>
                        <input type="password" 
                               name="map_api_key" 
                               id="map_api_key" 
                               class="form-control rounded-3 border-light-subtle @error('map_api_key') is-invalid @enderror" 
                               value="{{ old('map_api_key', $settings['api_key']) }}" 
                               placeholder="Masukkan Access Token / Private Key">
                        @error('map_api_key')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text text-muted mt-1 small">
                            Semua request dari Flutter akan dijaga/diproxy melalui Laravel untuk melindungi token ini agar tidak bocor ke client.
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-2">
                        <button type="button" id="btn-test-map" class="btn btn-light border rounded-3 px-4 fw-semibold text-secondary">
                            <i class="fas fa-plug me-2"></i>Cek Koneksi API
                        </button>
                        <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold">
                            <i class="fas fa-save me-2"></i>Simpan Perubahan
                        </button>
                    </div>
                </form>

                <div id="test-result" class="mt-4" style="display: none;">
                    <div class="alert alert-success rounded-3 p-3 border-0 shadow-sm" id="test-alert">
                        <h6 class="alert-heading fw-bold mb-1" id="test-title">Koneksi Berhasil</h6>
                        <p class="small mb-2" id="test-message">Berhasil memanggil Geocoding API untuk Surabaya.</p>
                        <div id="test-data" class="small p-2 bg-white rounded-3 border border-light-subtle" style="max-height: 150px; overflow-y: auto;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card bg-light border-0 rounded-3 shadow-none p-2">
            <div class="card-body">
                <h5 class="fw-bold text-dark fs-6 mb-3">Arsitektur Proxy Peta</h5>
                <p class="card-text small text-secondary">
                    Laravel bertindak sebagai <strong>Proxy & Security Guard</strong>. Endpoint client Flutter hanya menembak ke URL Laravel Anda, sehingga Token/Key peta aman sepenuhnya di server Anda.
                </p>
                <hr class="border-light-subtle my-3">
                <h6 class="small fw-bold text-dark mb-2">Endpoint yang di-Proxy:</h6>
                <ul class="small text-secondary ps-3 mb-0 d-flex flex-column gap-1">
                    <li><code>POST /api/shipping/map/routing</code> (Directions)</li>
                    <li><code>GET /api/shipping/map/autocomplete</code> (Pencarian POI)</li>
                    <li><code>POST /api/shipping/map/matrix</code> (Perhitungan Jarak)</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
    .uppercase { text-transform: uppercase; letter-spacing: 0.5px; font-size: 11px; }
    .btn-primary { background: #EC5B13; border: none; }
    .btn-primary:hover { background: #d44d0d; }
    .btn-light { background: #f8f9fa; border-color: #e9ecef; }
    .btn-light:hover { background: #e9ecef; }
    .form-control:focus { border-color: #EC5B13; box-shadow: 0 0 0 0.25rem rgba(236, 91, 19, 0.25); }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const btnTest = document.getElementById('btn-test-map');
        const testResult = document.getElementById('test-result');
        const testAlert = document.getElementById('test-alert');
        const testTitle = document.getElementById('test-title');
        const testMessage = document.getElementById('test-message');
        const testData = document.getElementById('test-data');
        const providerSelect = document.getElementById('map_provider');
        const baseUrlInput = document.getElementById('map_base_url');

        providerSelect.addEventListener('change', function() {
            if (this.value === 'mapbox') {
                baseUrlInput.value = 'https://api.mapbox.com';
            } else if (this.value === 'maplibre') {
                baseUrlInput.value = 'https://demotiles.maplibre.org';
            }
        });

        btnTest.addEventListener('click', function() {
            btnTest.disabled = true;
            btnTest.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Menghubungkan...';
            testResult.style.display = 'none';

            fetch("{{ route('admin.settings.map.test') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                testResult.style.display = 'block';
                testAlert.className = 'alert rounded-3 p-3 border-0 shadow-sm ' + (data.success ? 'alert-success bg-success-subtle text-success-emphasis' : 'alert-danger bg-danger-subtle text-danger-emphasis');
                testTitle.innerText = data.success ? 'Koneksi Berhasil' : 'Koneksi Gagal';
                testMessage.innerText = data.message;
                
                if (data.data && data.success) {
                    let html = '<strong>Sampel Hasil Pencarian (Surabaya):</strong><br>';
                    data.data.forEach(item => {
                        html += `- ${item.place_name || item.text}<br>`;
                    });
                    testData.innerHTML = html;
                    testData.style.display = 'block';
                } else {
                    testData.style.display = 'none';
                }
            })
            .catch(error => {
                testResult.style.display = 'block';
                testAlert.className = 'alert alert-danger bg-danger-subtle text-danger-emphasis rounded-3 p-3 border-0 shadow-sm';
                testTitle.innerText = 'Error';
                testMessage.innerText = 'Terjadi kesalahan sistem saat mencoba koneksi ke Map Server.';
                testData.style.display = 'none';
            })
            .finally(() => {
                btnTest.disabled = false;
                btnTest.innerHTML = '<i class="fas fa-plug me-2"></i>Cek Koneksi API';
            });
        });
    });
</script>
@endsection
