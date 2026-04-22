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

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
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
    .text-muted {
        color: #777;
    }
    .small {
        font-size: 0.875rem;
    }
    .me-1 { margin-right: 0.25rem; }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const providerSelect = document.getElementById('rajaongkir_provider');
        const accountTypeGroup = document.getElementById('account_type_group');
        
        function toggleAccountType() {
            if (providerSelect.value === 'komerce') {
                accountTypeGroup.style.display = 'none';
            } else {
                accountTypeGroup.style.display = 'block';
            }
        }
        
        providerSelect.addEventListener('change', toggleAccountType);
        toggleAccountType();
    });
</script>
@endsection
