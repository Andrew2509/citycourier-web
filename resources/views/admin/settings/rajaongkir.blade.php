@extends('layouts.admin')

@section('title', 'RajaOngkir Provider Settings')
@section('page-title', 'Provider RajaOngkir')
@section('page-subtitle', 'Kelola konfigurasi API RajaOngkir untuk cek ongkir')

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Konfigurasi API RajaOngkir</h3>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.settings.rajaongkir.update') }}" method="POST">
                    @csrf
                    
                    <div class="form-group mb-4">
                        <label for="rajaongkir_api_key" class="form-label">RajaOngkir API Key</label>
                        <input type="text" 
                               name="rajaongkir_api_key" 
                               id="rajaongkir_api_key" 
                               class="form-control @error('rajaongkir_api_key') is-invalid @enderror" 
                               value="{{ old('rajaongkir_api_key', $settings['api_key']) }}" 
                               placeholder="Masukkan API Key dari dashboard RajaOngkir">
                        @error('rajaongkir_api_key')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <p class="form-text text-muted mt-1">
                            Anda bisa mendapatkan API Key dari dashboard akun <strong>RajaOngkir</strong> Anda.
                        </p>
                    </div>

                    <div class="form-group mb-4">
                        <label for="rajaongkir_provider" class="form-label">Service Provider</label>
                        <select name="rajaongkir_provider" id="rajaongkir_provider" class="form-control @error('rajaongkir_provider') is-invalid @enderror">
                            <option value="rajaongkir" {{ old('rajaongkir_provider', $settings['provider'] ?? 'rajaongkir') == 'rajaongkir' ? 'selected' : '' }}>RajaOngkir (Official)</option>
                            <option value="komerce" {{ old('rajaongkir_provider', $settings['provider'] ?? 'rajaongkir') == 'komerce' ? 'selected' : '' }}>Komerce (RajaOngkir v2)</option>
                        </select>
                        @error('rajaongkir_provider')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <p class="form-text text-muted mt-1">
                            Pilih provider yang akan digunakan. <strong>Komerce</strong> mendukung pencarian hingga tingkat Kelurahan/Desa.
                        </p>
                    </div>

                    <div class="form-group mb-4" id="account_type_group">
                        <label for="rajaongkir_account_type" class="form-label">Tipe Akun</label>
                        <select name="rajaongkir_account_type" id="rajaongkir_account_type" class="form-control @error('rajaongkir_account_type') is-invalid @enderror">
                            <option value="starter" {{ old('rajaongkir_account_type', $settings['account_type']) == 'starter' ? 'selected' : '' }}>Starter (Gratis)</option>
                            <option value="basic" {{ old('rajaongkir_account_type', $settings['account_type']) == 'basic' ? 'selected' : '' }}>Basic (Berbayar)</option>
                            <option value="pro" {{ old('rajaongkir_account_type', $settings['account_type']) == 'pro' ? 'selected' : '' }}>Pro (Berbayar)</option>
                        </select>
                        @error('rajaongkir_account_type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <p class="form-text text-muted mt-1">
                            Pilih tipe akun RajaOngkir yang Anda gunakan. (Hanya berlaku untuk provider RajaOngkir Official)
                        </p>
                    </div>

                    <div class="mb-4" id="sandbox_mode_group">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="rajaongkir_sandbox" id="rajaongkir_sandbox" value="1" {{ \App\Models\Setting::get('rajaongkir_sandbox') ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold" for="rajaongkir_sandbox">Sandbox Mode (Khusus Komerce)</label>
                        </div>
                        <p class="text-muted small">Aktifkan jika Anda menggunakan API Key dari lingkungan Testing/Sandbox Komerce.</p>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="button" id="btn-test-connection" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-plug me-1"></i> Cek Koneksi
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>

                <div id="test-result" class="mt-4" style="display: none;">
                    <div class="alert" id="test-alert">
                        <h6 class="alert-heading fw-bold mb-1" id="test-title"></h6>
                        <p class="small mb-2" id="test-message"></p>
                        <div id="test-data" class="small p-2 bg-white rounded border" style="max-height: 150px; overflow-y: auto;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card bg-light border-0">
            <div class="card-body">
                <h5 class="card-title">Informasi Integrasi</h5>
                <p class="card-text small">
                    Integrasi RajaOngkir digunakan untuk menghitung biaya pengiriman secara otomatis berdasarkan berat paket dan lokasi pengirim/penerima.
                </p>
                <hr>
                <h6 class="small fw-bold">Ketentuan Tipe Akun:</h6>
                <ul class="small ps-3 mb-0">
                    <li><strong>Starter</strong>: Mendukung JNE, POS, TIKI. Hanya sampai tingkat kota.</li>
                    <li><strong>Basic</strong>: Lebih banyak kurir. Hanya sampai tingkat kota.</li>
                    <li><strong>Pro</strong>: Mendukung hingga tingkat kecamatan.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
    .card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        border: 1px solid #eee;
        margin-bottom: 24px;
    }
    .card-header {
        padding: 20px;
        border-bottom: 1px solid #f0f0f0;
        background: transparent;
    }
    .card-title {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 600;
        color: #333;
    }
    .card-body {
        padding: 24px;
    }
    .form-label {
        font-weight: 500;
        margin-bottom: 8px;
        display: block;
        color: #444;
    }
    .form-control {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid #ddd;
        border-radius: 8px;
        transition: border-color 0.2s;
    }
    .form-control:focus {
        border-color: #EC5B13;
        outline: none;
    }
    .btn-primary {
        background: #EC5B13;
        border: none;
        color: #fff;
        padding: 10px 24px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
    }
    .btn-primary:hover {
        background: #d44d0d;
    }
    .btn-outline-secondary {
        background: transparent;
        border: 1px solid #ddd;
        color: #666;
        padding: 10px 24px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-outline-secondary:hover {
        background: #f8f9fa;
        border-color: #ccc;
    }
    .text-muted {
        color: #777;
    }
    .alert-success {
        background-color: #d1e7dd;
        border-color: #badbcc;
        color: #0f5132;
    }
    .alert-danger {
        background-color: #f8d7da;
        border-color: #f5c2c7;
        color: #842029;
    }
    .small {
        font-size: 0.875rem;
    }
    .me-1 { margin-right: 0.25rem; }
    .me-2 { margin-right: 0.5rem; }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const providerSelect = document.getElementById('rajaongkir_provider');
        const accountTypeGroup = document.getElementById('account_type_group');
        const sandboxModeGroup = document.getElementById('sandbox_mode_group');
        const btnTest = document.getElementById('btn-test-connection');
        const testResult = document.getElementById('test-result');
        const testAlert = document.getElementById('test-alert');
        const testTitle = document.getElementById('test-title');
        const testMessage = document.getElementById('test-message');
        const testData = document.getElementById('test-data');
        
        function toggleAccountType() {
            if (providerSelect.value === 'komerce') {
                accountTypeGroup.style.display = 'none';
                sandboxModeGroup.style.display = 'block';
            } else {
                accountTypeGroup.style.display = 'block';
                sandboxModeGroup.style.display = 'none';
            }
        }
        
        providerSelect.addEventListener('change', toggleAccountType);
        toggleAccountType();

        btnTest.addEventListener('click', function() {
            btnTest.disabled = true;
            btnTest.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Menghubungkan...';
            testResult.style.display = 'none';

            fetch("{{ route('admin.settings.rajaongkir.test') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                testResult.style.display = 'block';
                testAlert.className = 'alert ' + (data.success ? 'alert-success' : 'alert-danger');
                testTitle.innerText = data.success ? 'Koneksi Berhasil' : 'Koneksi Gagal';
                testMessage.innerText = data.message;
                
                if (data.data) {
                    let html = '<strong>Sampel Data Provinsi:</strong><br>';
                    data.data.forEach(item => {
                        html += `- ${item.province || item.province_name}<br>`;
                    });
                    testData.innerHTML = html;
                    testData.style.display = 'block';
                } else {
                    testData.style.display = 'none';
                }
            })
            .catch(error => {
                testResult.style.display = 'block';
                testAlert.className = 'alert alert-danger';
                testTitle.innerText = 'Error';
                testMessage.innerText = 'Terjadi kesalahan sistem saat mencoba koneksi.';
                testData.style.display = 'none';
            })
            .finally(() => {
                btnTest.disabled = false;
                btnTest.innerHTML = '<i class="fas fa-plug me-1"></i> Cek Koneksi';
            });
        });
    });
</script>
@endsection
