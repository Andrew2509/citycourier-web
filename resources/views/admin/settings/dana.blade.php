@extends('layouts.admin')

@section('title', 'DANA Provider Settings')
@section('page-title', 'Pengaturan Provider DANA')
@section('page-subtitle', 'Kelola kredensial & mode integrasi DANA Widget Binding (mock, sandbox, atau production)')

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card bg-white shadow-sm rounded-3">
            <div class="card-header border-0 bg-transparent pt-4 px-4">
                <h3 class="card-title fw-bold text-dark fs-5">Konfigurasi DANA</h3>
            </div>
            <div class="card-body px-4 pb-4">
                <form action="{{ route('admin.settings.dana.update') }}" method="POST">
                    @csrf

                    <div class="form-group mb-4">
                        <label for="dana_mode" class="form-label fw-semibold text-muted small uppercase">Mode Provider</label>
                        <select name="dana_mode" id="dana_mode" class="form-control rounded-3 border-light-subtle @error('dana_mode') is-invalid @enderror">
                            <option value="mock" {{ old('dana_mode', $settings['mode'] ?? 'mock') == 'mock' ? 'selected' : '' }}>Mock (Development - Tanpa Koneksi Asli)</option>
                            <option value="sandbox" {{ old('dana_mode', $settings['mode'] ?? 'mock') == 'sandbox' ? 'selected' : '' }}>Sandbox (Testing DANA)</option>
                            <option value="production" {{ old('dana_mode', $settings['mode'] ?? 'mock') == 'production' ? 'selected' : '' }}>Production (Live)</option>
                        </select>
                        @error('dana_mode')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group mb-4">
                        <label for="dana_api_base_url" class="form-label fw-semibold text-muted small uppercase">API Base URL</label>
                        <input type="url" name="dana_api_base_url" id="dana_api_base_url" class="form-control rounded-3 border-light-subtle @error('dana_api_base_url') is-invalid @enderror" value="{{ old('dana_api_base_url', $settings['api_base_url']) }}" placeholder="https://api.sandbox.dana.id">
                        @error('dana_api_base_url')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group mb-4">
                        <label for="dana_client_id" class="form-label fw-semibold text-muted small uppercase">Client ID (X-PARTNER-ID)</label>
                        <input type="text" name="dana_client_id" id="dana_client_id" class="form-control rounded-3 border-light-subtle @error('dana_client_id') is-invalid @enderror" value="{{ old('dana_client_id', $settings['client_id']) }}">
                        @error('dana_client_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group mb-4">
                        <label for="dana_client_secret" class="form-label fw-semibold text-muted small uppercase">Client Secret</label>
                        <input type="password" name="dana_client_secret" id="dana_client_secret" class="form-control rounded-3 border-light-subtle @error('dana_client_secret') is-invalid @enderror" value="{{ old('dana_client_secret', $settings['client_secret']) }}">
                        @error('dana_client_secret')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group mb-4">
                        <label for="dana_merchant_id" class="form-label fw-semibold text-muted small uppercase">Merchant ID</label>
                        <input type="text" name="dana_merchant_id" id="dana_merchant_id" class="form-control rounded-3 border-light-subtle @error('dana_merchant_id') is-invalid @enderror" value="{{ old('dana_merchant_id', $settings['merchant_id']) }}">
                        @error('dana_merchant_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group mb-4">
                        <label for="dana_callback_url" class="form-label fw-semibold text-muted small uppercase">Callback URL</label>
                        <input type="url" name="dana_callback_url" id="dana_callback_url" class="form-control rounded-3 border-light-subtle @error('dana_callback_url') is-invalid @enderror" value="{{ old('dana_callback_url', $settings['callback_url']) }}" placeholder="https://citycourier.pabm.space/api/courier/dana/callback">
                        @error('dana_callback_url')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group mb-4">
                        <label for="dana_private_key" class="form-label fw-semibold text-muted small uppercase">Private Key (RSA PKCS#8)</label>
                        <textarea name="dana_private_key" id="dana_private_key" rows="6" class="form-control rounded-3 border-light-subtle font-monospace small @error('dana_private_key') is-invalid @enderror" placeholder="-----BEGIN PRIVATE KEY-----&#10;...&#10;-----END PRIVATE KEY-----">{{ old('dana_private_key', $settings['private_key']) }}</textarea>
                        @error('dana_private_key')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text text-muted mt-1 small">Private key digunakan untuk menandatangani permintaan (X-SIGNATURE) ke DANA. Dijaga aman di server.</div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-2">
                        <button type="button" id="btn-test-dana" class="btn btn-light border rounded-3 px-4 fw-semibold text-secondary">
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
                        <p class="small mb-2" id="test-message">Berhasil membuat URL binding DANA.</p>
                        <div id="test-data" class="small p-2 bg-white rounded-3 border border-light-subtle" style="max-height: 150px; overflow-y: auto;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card bg-light border-0 rounded-3 shadow-none p-2">
            <div class="card-body">
                <h5 class="fw-bold text-dark fs-6 mb-3">Alur DANA Widget Binding</h5>
                <ol class="small text-secondary ps-3 mb-0 d-flex flex-column gap-2">
                    <li>Aplikasi kurir memanggil <code>POST /api/courier/dana/connect</code>.</li>
                    <li>Backend membuat <strong>Deeplink Binding URL</strong> DANA.</li>
                    <li>Kurir membuka URL tersebut (aplikasi/web DANA).</li>
                    <li>DANA me-redirect ke <code>callback_url</code> dengan <code>auth_code</code>.</li>
                    <li>Backend menukar <code>auth_code</code> menjadi access token &amp; menyimpan koneksi.</li>
                </ol>
                <hr class="border-light-subtle my-3">
                <h6 class="small fw-bold text-dark mb-2">Mode</h6>
                <ul class="small text-secondary ps-3 mb-0 d-flex flex-column gap-1">
                    <li><strong>Mock</strong> - simulasi tanpa koneksi asli (untuk development).</li>
                    <li><strong>Sandbox</strong> - testing di lingkungan DANA sandbox.</li>
                    <li><strong>Production</strong> - live (perlu kredensial production).</li>
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
        const btnTest = document.getElementById('btn-test-dana');
        const testResult = document.getElementById('test-result');
        const testAlert = document.getElementById('test-alert');
        const testTitle = document.getElementById('test-title');
        const testMessage = document.getElementById('test-message');
        const testData = document.getElementById('test-data');

        btnTest.addEventListener('click', function() {
            btnTest.disabled = true;
            btnTest.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Menghubungkan...';
            testResult.style.display = 'none';

            fetch("{{ route('admin.settings.dana.test') }}", {
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

                if (data.detail) {
                    testData.innerHTML = '<strong>Detail:</strong><br><code class="small">' + data.detail + '</code>';
                    testData.style.display = 'block';
                } else if (data.data && data.data.redirect_url) {
                    testData.innerHTML = '<strong>URL Binding:</strong><br><code class="small">' + data.data.redirect_url + '</code>';
                    testData.style.display = 'block';
                } else {
                    testData.style.display = 'none';
                }
            })
            .catch(error => {
                testResult.style.display = 'block';
                testAlert.className = 'alert alert-danger bg-danger-subtle text-danger-emphasis rounded-3 p-3 border-0 shadow-sm';
                testTitle.innerText = 'Error';
                testMessage.innerText = 'Terjadi kesalahan sistem saat mencoba koneksi ke DANA.';
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
