@extends('layouts.admin')

@section('title', 'Komerce Payment Settings')
@section('page-title', 'Layanan Pembayaran')
@section('page-subtitle', 'Kelola konfigurasi API Komerce untuk Virtual Account dan QRIS')

@section('content')
<div class="row">
    {{-- ─── Kolom Kiri: Form Utama ─────────────────────────────── --}}
    <div class="col-md-8">

        {{-- Alert sukses / error --}}
        @if (session('success'))
            <div class="alert alert-success d-flex align-items-center mb-4" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                {{ session('error') }}
            </div>
        @endif

        <div class="card">
            <div class="card-header d-flex align-items-center">
                <div class="icon-box me-3">
                    <i class="fas fa-credit-card" style="color:#FA880F;font-size:1.3rem;"></i>
                </div>
                <div>
                    <h3 class="card-title mb-0">Konfigurasi API Komerce Payment</h3>
                    <p class="text-muted small mb-0">Pembayaran via Virtual Account (VA) dan QRIS</p>
                </div>
            </div>

            <div class="card-body">
                <form action="{{ route('admin.settings.payment.update') }}" method="POST" id="form-payment">
                    @csrf

                    {{-- API Key --}}
                    <div class="form-group mb-4">
                        <label for="komerce_payment_api_key" class="form-label">
                            Komerce Payment API Key
                            <span class="badge-required">Wajib</span>
                        </label>
                        <div class="input-group">
                            <input type="password"
                                   name="komerce_payment_api_key"
                                   id="komerce_payment_api_key"
                                   class="form-control @error('komerce_payment_api_key') is-invalid @enderror"
                                   value="{{ old('komerce_payment_api_key', $settings['api_key']) }}"
                                   placeholder="Masukkan API Key dari dashboard Komerce / RajaOngkir"
                                   autocomplete="off">
                            <button type="button" class="btn-eye" id="btn-toggle-key" title="Tampilkan/Sembunyikan">
                                <i class="fas fa-eye" id="eye-icon"></i>
                            </button>
                        </div>
                        @error('komerce_payment_api_key')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        <p class="form-text text-muted mt-1">
                            Dapatkan API Key dari
                            <a href="https://collaborator.komerce.id" target="_blank" class="link-orange">
                                collaborator.komerce.id
                            </a>
                            → Integration → API Key (API Key ini sama dengan RajaOngkir).
                        </p>
                    </div>

                    {{-- Environment --}}
                    <div class="form-group mb-4">
                        <label class="form-label">Environment (Mode)</label>
                        <div class="env-selector">
                            <label class="env-option" id="env-sandbox">
                                <input type="radio" name="komerce_payment_env" value="sandbox"
                                    {{ old('komerce_payment_env', $settings['env']) === 'sandbox' ? 'checked' : '' }}>
                                <div class="env-card">
                                    <i class="fas fa-flask env-icon"></i>
                                    <span class="env-name">Sandbox</span>
                                    <span class="env-desc">Testing & Development</span>
                                </div>
                            </label>
                            <label class="env-option" id="env-production">
                                <input type="radio" name="komerce_payment_env" value="production"
                                    {{ old('komerce_payment_env', $settings['env']) === 'production' ? 'checked' : '' }}>
                                <div class="env-card">
                                    <i class="fas fa-rocket env-icon"></i>
                                    <span class="env-name">Production</span>
                                    <span class="env-desc">Live & Nyata</span>
                                </div>
                            </label>
                        </div>
                        @error('komerce_payment_env')
                            <div class="invalid-feedback d-block mt-1">{{ $message }}</div>
                        @enderror
                        <p class="form-text text-muted mt-2">
                            Gunakan <strong>Sandbox</strong> saat development. Ganti ke <strong>Production</strong> saat live.
                        </p>
                    </div>

                    {{-- Callback Key --}}
                    <div class="form-group mb-4">
                        <label for="komerce_payment_callback_key" class="form-label">
                            Callback Key
                            <span class="badge-optional">Opsional</span>
                        </label>
                        <input type="text"
                               name="komerce_payment_callback_key"
                               id="komerce_payment_callback_key"
                               class="form-control @error('komerce_payment_callback_key') is-invalid @enderror"
                               value="{{ old('komerce_payment_callback_key', $settings['callback_key']) }}"
                               placeholder="Key untuk verifikasi webhook Komerce (opsional)">
                        @error('komerce_payment_callback_key')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <p class="form-text text-muted mt-1">
                            Digunakan untuk memverifikasi bahwa notifikasi pembayaran benar-benar dari Komerce.
                            Webhook URL: <code>{{ config('app.url') }}/api/payment/callback</code>
                        </p>
                    </div>

                    {{-- Tombol --}}
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" id="btn-test" class="btn btn-outline-secondary">
                            <i class="fas fa-plug me-1"></i> Cek Koneksi
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>

                {{-- Hasil Test Koneksi --}}
                <div id="test-result" class="mt-4" style="display:none;">
                    <div class="alert" id="test-alert">
                        <h6 class="alert-heading fw-bold mb-1" id="test-title"></h6>
                        <p class="small mb-2" id="test-message"></p>
                        <div id="test-data" class="small p-2 bg-white rounded border" style="max-height:200px;overflow-y:auto;display:none;"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card Informasi Endpoint --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">📡 Endpoint API yang Digunakan</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table-info-api">
                        <thead>
                            <tr>
                                <th>Method</th>
                                <th>Endpoint App</th>
                                <th>Fungsi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge-get">GET</span></td>
                                <td><code>/api/payment/methods</code></td>
                                <td>Ambil daftar VA & QRIS</td>
                            </tr>
                            <tr>
                                <td><span class="badge-post">POST</span></td>
                                <td><code>/api/payment/create</code></td>
                                <td>Buat transaksi pembayaran</td>
                            </tr>
                            <tr>
                                <td><span class="badge-get">GET</span></td>
                                <td><code>/api/payment/{id}/status</code></td>
                                <td>Cek status pembayaran</td>
                            </tr>
                            <tr>
                                <td><span class="badge-post">POST</span></td>
                                <td><code>/api/payment/{id}/cancel</code></td>
                                <td>Batalkan pembayaran</td>
                            </tr>
                            <tr>
                                <td><span class="badge-post">POST</span></td>
                                <td><code>/api/payment/callback</code></td>
                                <td>Webhook dari Komerce (public)</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ─── Kolom Kanan: Info Panel ─────────────────────────────── --}}
    <div class="col-md-4">

        {{-- Status Konfigurasi --}}
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">📊 Status Konfigurasi</h5>
                <div class="status-item">
                    <span class="status-label">API Key</span>
                    <span class="status-value {{ $settings['api_key'] ? 'status-ok' : 'status-missing' }}">
                        {{ $settings['api_key'] ? '✓ Terkonfigurasi' : '✗ Belum diisi' }}
                    </span>
                </div>
                <div class="status-item">
                    <span class="status-label">Environment</span>
                    <span class="status-value {{ $settings['env'] === 'production' ? 'status-prod' : 'status-sandbox' }}">
                        {{ strtoupper($settings['env'] ?? 'sandbox') }}
                    </span>
                </div>
                <div class="status-item">
                    <span class="status-label">Callback Key</span>
                    <span class="status-value {{ $settings['callback_key'] ? 'status-ok' : 'status-optional' }}">
                        {{ $settings['callback_key'] ? '✓ Terkonfigurasi' : '— Opsional' }}
                    </span>
                </div>
                <div class="status-item">
                    <span class="status-label">Base URL</span>
                    <span class="status-value status-url">
                        {{ ($settings['env'] ?? 'sandbox') === 'production' ? 'api.komerce.id' : 'api-sandbox.komerce.id' }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Panduan --}}
        <div class="card bg-light border-0">
            <div class="card-body">
                <h5 class="card-title">📖 Cara Mendapatkan API Key</h5>
                <ol class="guide-list">
                    <li>Buka <a href="https://collaborator.komerce.id" target="_blank" class="link-orange">collaborator.komerce.id</a></li>
                    <li>Login dengan akun RajaOngkir Anda</li>
                    <li>Menu <strong>Integration → API Key</strong></li>
                    <li>Salin API Key dan tempel di form ini</li>
                </ol>
                <hr>
                <h6 class="small fw-bold">Metode Pembayaran Tersedia:</h6>
                <ul class="payment-method-list">
                    <li><i class="fas fa-university"></i> Virtual Account BCA, BNI, BRI, Mandiri</li>
                    <li><i class="fas fa-university"></i> VA Permata, CIMB, BSI</li>
                    <li><i class="fas fa-qrcode"></i> QRIS (semua e-wallet)</li>
                </ul>
                <hr>
                <p class="small text-muted mb-0">
                    💡 API Key untuk <strong>Payment</strong> adalah key yang <strong>sama</strong> dengan RajaOngkir (Komerce), bukan key yang berbeda.
                </p>
            </div>
        </div>
    </div>
</div>

<style>
    .card {
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.06);
        border: 1px solid #f0f0f0;
        margin-bottom: 24px;
    }
    .card-header {
        padding: 20px 24px;
        border-bottom: 1px solid #f5f5f5;
        background: transparent;
        display: flex;
        align-items: center;
    }
    .card-title {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 600;
        color: #2D3748;
    }
    .card-body { padding: 24px; }

    /* Icon box */
    .icon-box {
        width: 44px; height: 44px;
        background: #FFF4EB;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }

    /* Form labels */
    .form-label {
        font-weight: 600;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
        color: #2D3748;
    }
    .badge-required {
        font-size: 10px; padding: 2px 8px;
        background: #FFF0F0; color: #E53E3E;
        border-radius: 10px; font-weight: 600;
    }
    .badge-optional {
        font-size: 10px; padding: 2px 8px;
        background: #F0FFF4; color: #38A169;
        border-radius: 10px; font-weight: 600;
    }

    /* Input */
    .input-group { display: flex; }
    .form-control {
        width: 100%; padding: 10px 14px;
        border: 1.5px solid #E2E8F0;
        border-radius: 8px; font-size: 14px;
        transition: border-color 0.2s;
    }
    .input-group .form-control { border-radius: 8px 0 0 8px; }
    .form-control:focus { border-color: #FA880F; outline: none; }
    .form-control.is-invalid { border-color: #E53E3E; }

    /* Eye button */
    .btn-eye {
        padding: 0 14px;
        background: #F7FAFC; border: 1.5px solid #E2E8F0;
        border-left: none; border-radius: 0 8px 8px 0;
        cursor: pointer; color: #718096;
        transition: background 0.2s;
    }
    .btn-eye:hover { background: #EDF2F7; }

    /* Environment Selector */
    .env-selector { display: flex; gap: 12px; }
    .env-option { flex: 1; cursor: pointer; }
    .env-option input { display: none; }
    .env-card {
        border: 2px solid #E2E8F0;
        border-radius: 12px; padding: 16px 12px;
        text-align: center; transition: all 0.2s;
        display: flex; flex-direction: column; align-items: center; gap: 4px;
    }
    .env-option input:checked + .env-card {
        border-color: #FA880F;
        background: #FFF4EB;
    }
    .env-icon { font-size: 22px; color: #718096; margin-bottom: 2px; }
    .env-option input:checked + .env-card .env-icon { color: #FA880F; }
    .env-name { font-weight: 700; font-size: 14px; color: #2D3748; }
    .env-desc { font-size: 11px; color: #718096; }

    /* Buttons */
    .btn-primary {
        background: #FA880F; border: none; color: #fff;
        padding: 10px 24px; border-radius: 8px;
        font-weight: 600; cursor: pointer; transition: background 0.2s;
    }
    .btn-primary:hover { background: #e07609; }
    .btn-outline-secondary {
        background: transparent; border: 1.5px solid #E2E8F0;
        color: #555; padding: 10px 24px; border-radius: 8px;
        font-weight: 600; cursor: pointer; transition: all 0.2s;
    }
    .btn-outline-secondary:hover { background: #F7FAFC; }
    .gap-2 { gap: 8px; }

    /* Alert */
    .alert { padding: 14px 16px; border-radius: 10px; }
    .alert-success { background: #F0FFF4; border: 1px solid #C6F6D5; color: #276749; }
    .alert-danger  { background: #FFF5F5; border: 1px solid #FED7D7; color: #C53030; }

    /* Status items */
    .status-item {
        display: flex; justify-content: space-between; align-items: center;
        padding: 10px 0; border-bottom: 1px solid #F7FAFC;
    }
    .status-item:last-child { border-bottom: none; }
    .status-label { font-size: 13px; color: #718096; }
    .status-value { font-size: 12px; font-weight: 600; }
    .status-ok       { color: #38A169; }
    .status-missing  { color: #E53E3E; }
    .status-sandbox  { color: #DD6B20; background: #FFFAF0; padding: 2px 8px; border-radius: 4px; }
    .status-prod     { color: #E53E3E; background: #FFF5F5; padding: 2px 8px; border-radius: 4px; }
    .status-optional { color: #A0AEC0; }
    .status-url      { color: #4A5568; font-size: 11px; font-family: monospace; }

    /* Guide list */
    .guide-list { padding-left: 18px; margin: 0; }
    .guide-list li { margin-bottom: 6px; font-size: 13px; color: #4A5568; }

    /* Payment method list */
    .payment-method-list { list-style: none; padding: 0; margin: 0; }
    .payment-method-list li {
        padding: 4px 0; font-size: 12px; color: #4A5568;
        display: flex; align-items: center; gap: 8px;
    }
    .payment-method-list li i { color: #FA880F; width: 14px; }

    /* API table */
    .table-info-api { width: 100%; border-collapse: collapse; font-size: 13px; }
    .table-info-api th {
        padding: 8px 12px; text-align: left;
        background: #F7FAFC; font-weight: 600; color: #4A5568;
        border-bottom: 2px solid #E2E8F0;
    }
    .table-info-api td {
        padding: 8px 12px; border-bottom: 1px solid #F0F0F0;
        vertical-align: middle;
    }
    .table-info-api code {
        background: #F7FAFC; padding: 2px 6px;
        border-radius: 4px; font-size: 12px; color: #DD6B20;
    }
    .badge-get  { background: #EBF8FF; color: #2B6CB0; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; }
    .badge-post { background: #F0FFF4; color: #276749; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; }

    .link-orange { color: #FA880F; text-decoration: none; }
    .link-orange:hover { text-decoration: underline; }

    .small { font-size: 0.85rem; }
    .text-muted { color: #718096; }
    .me-1 { margin-right: 0.25rem; }
    .me-2 { margin-right: 0.5rem; }
    .me-3 { margin-right: 1rem; }
    .mb-0 { margin-bottom: 0; }
    .mb-4 { margin-bottom: 1.5rem; }
    .mt-1 { margin-top: 0.25rem; }
    .mt-2 { margin-top: 0.5rem; }
    .mt-4 { margin-top: 1.5rem; }
    .d-flex { display: flex; }
    .justify-content-end { justify-content: flex-end; }
    .align-items-center { align-items: center; }
    .d-block { display: block; }
    .fw-bold { font-weight: 700; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── Toggle visibility API Key ──────────────────────────────
    const keyInput = document.getElementById('komerce_payment_api_key');
    const btnToggle = document.getElementById('btn-toggle-key');
    const eyeIcon = document.getElementById('eye-icon');

    btnToggle.addEventListener('click', function () {
        if (keyInput.type === 'password') {
            keyInput.type = 'text';
            eyeIcon.className = 'fas fa-eye-slash';
        } else {
            keyInput.type = 'password';
            eyeIcon.className = 'fas fa-eye';
        }
    });

    // ── Test Koneksi ───────────────────────────────────────────
    const btnTest    = document.getElementById('btn-test');
    const testResult = document.getElementById('test-result');
    const testAlert  = document.getElementById('test-alert');
    const testTitle  = document.getElementById('test-title');
    const testMsg    = document.getElementById('test-message');
    const testData   = document.getElementById('test-data');

    btnTest.addEventListener('click', function () {
        // Simpan form dulu agar setting terbaru dipakai saat test
        const apiKey = document.getElementById('komerce_payment_api_key').value;
        if (!apiKey) {
            alert('Masukkan API Key terlebih dahulu sebelum cek koneksi.');
            return;
        }

        btnTest.disabled = true;
        btnTest.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Mengecek...';
        testResult.style.display = 'none';

        fetch("{{ route('admin.settings.payment.test') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(r => r.json())
        .then(data => {
            testResult.style.display = 'block';
            testAlert.className = 'alert ' + (data.success ? 'alert-success' : 'alert-danger');
            testTitle.innerText = data.success ? '✓ Koneksi Berhasil' : '✗ Koneksi Gagal';
            testMsg.innerText = data.message;

            if (data.data && data.data.length > 0) {
                let html = '<strong>Metode Pembayaran Tersedia:</strong><br>';
                data.data.forEach(item => {
                    const name = item.display_name || item.bank_code || item.payment_type || JSON.stringify(item);
                    html += `• ${name}<br>`;
                });
                testData.innerHTML = html;
                testData.style.display = 'block';
            } else {
                testData.style.display = 'none';
            }
        })
        .catch(() => {
            testResult.style.display = 'block';
            testAlert.className = 'alert alert-danger';
            testTitle.innerText = '✗ Error';
            testMsg.innerText = 'Terjadi kesalahan jaringan saat mencoba koneksi.';
            testData.style.display = 'none';
        })
        .finally(() => {
            btnTest.disabled = false;
            btnTest.innerHTML = '<i class="fas fa-plug me-1"></i> Cek Koneksi';
        });
    });

    // ── Highlight env card saat berubah ───────────────────────
    document.querySelectorAll('input[name="komerce_payment_env"]').forEach(radio => {
        radio.addEventListener('change', function () {
            document.querySelectorAll('.env-card').forEach(c => c.style.borderColor = '#E2E8F0');
            if (this.checked) {
                this.nextElementSibling.style.borderColor = '#FA880F';
            }
        });
    });
});
</script>
@endsection
