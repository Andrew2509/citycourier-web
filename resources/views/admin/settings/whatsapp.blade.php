@extends('layouts.admin')

@section('title', 'WhatsApp Provider Settings')
@section('page-title', 'Provider WhatsApp')
@section('page-subtitle', 'Kelola konfigurasi API OrbitWA')

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Konfigurasi API OrbitWA</h3>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.settings.whatsapp.update') }}" method="POST">
                    @csrf
                    
                    <div class="form-group mb-4">
                        <label for="orbitwa_api_key" class="form-label">OrbitWA API Key</label>
                        <input type="text" 
                               name="orbitwa_api_key" 
                               id="orbitwa_api_key" 
                               class="form-control @error('orbitwa_api_key') is-invalid @enderror" 
                               value="{{ old('orbitwa_api_key', $settings['api_key']) }}" 
                               placeholder="Masukkan API Key dari dashboard OrbitWA">
                        @error('orbitwa_api_key')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <p class="form-text text-muted mt-1">
                            Anda bisa mendapatkan API Key dari menu <strong>Profile</strong> atau <strong>API</strong> di dashboard OrbitWA.
                        </p>
                    </div>

                    <div class="form-group mb-4">
                        <label for="orbitwa_device_id" class="form-label">ID Device</label>
                        <input type="text" 
                               name="orbitwa_device_id" 
                               id="orbitwa_device_id" 
                               class="form-control @error('orbitwa_device_id') is-invalid @enderror" 
                               value="{{ old('orbitwa_device_id', $settings['device_id']) }}" 
                               placeholder="Contoh: 1">
                        @error('orbitwa_device_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <p class="form-text text-muted mt-1">
                            Masukkan ID Device yang terdaftar di dashboard OrbitWA Anda.
                        </p>
                    </div>

                    <div class="form-group mb-4">
                        <label for="orbitwa_base_url" class="form-label">Base URL API</label>
                        <input type="url" 
                               name="orbitwa_base_url" 
                               id="orbitwa_base_url" 
                               class="form-control @error('orbitwa_base_url') is-invalid @enderror" 
                               value="{{ old('orbitwa_base_url', $settings['base_url']) }}" 
                               placeholder="Contoh: https://orbitwaapi.site/api/v1">
                        @error('orbitwa_base_url')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <p class="form-text text-muted mt-1">
                            Secara default adalah <code>https://orbitwaapi.site/api/v1</code>.
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
                    Integrasi ini digunakan untuk mengirimkan kode OTP melalui WhatsApp kepada pengguna saat proses Login/Registrasi lewat nomor HP.
                </p>
                <hr>
                <h6 class="small fw-bold">Tips:</h6>
                <ul class="small ps-3 mb-0">
                    <li>Pastikan status perangkat WhatsApp Anda <strong>Connected</strong> di dashboard OrbitWA.</li>
                    <li>Gunakan format nomor HP internasional (tanpa +) jika memungkinkan, namun sistem akan otomatis memformat nomor Indonesia (62).</li>
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
    .ms-1 { margin-left: 0.25rem; }
    .me-1 { margin-right: 0.25rem; }
</style>
@endsection
