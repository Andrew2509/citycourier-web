@extends('layouts.admin')

@section('title', 'Pengaturan Provider DANA')

@section('content')
<div class="space-y-5">
    {{-- Header Halaman --}}
    <div class="page-header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.settings.providers') }}" class="back-link" title="Kembali ke Daftar Provider">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            </a>
            <div>
                <h1 class="page-title">Pengaturan Provider DANA</h1>
                <p class="page-subtitle">Konfigurasi kredensial gateway dompet digital DANA untuk penerimaan transaksi non-tunai.</p>
            </div>
        </div>

        <div class="page-actions">
            <span class="badge {{ ($settings['mode'] ?? 'mock') === 'production' ? 'badge-green' : 'badge-amber' }}">
                Mode: {{ strtoupper($settings['mode'] ?? 'MOCK') }}
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {{-- Form Kredensial (2 Kolom) --}}
        <div class="lg:col-span-2 card">
            <div class="card-header">
                <div class="card-title">Kredensial API Merchant DANA</div>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.settings.dana.update') }}" method="POST" class="space-y-4">
                    @csrf

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label" for="dana_mode">Environment Mode <span class="text-red-500">*</span></label>
                            <select name="dana_mode" id="dana_mode" class="form-select">
                                <option value="mock" {{ old('dana_mode', $settings['mode'] ?? '') === 'mock' ? 'selected' : '' }}>Mock (Simulasi Lokal)</option>
                                <option value="sandbox" {{ old('dana_mode', $settings['mode'] ?? '') === 'sandbox' ? 'selected' : '' }}>Sandbox (Testing DANA)</option>
                                <option value="production" {{ old('dana_mode', $settings['mode'] ?? '') === 'production' ? 'selected' : '' }}>Production (Live)</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label" for="dana_api_base_url">API Base URL</label>
                            <input type="text"
                                   name="dana_api_base_url"
                                   id="dana_api_base_url"
                                   value="{{ old('dana_api_base_url', $settings['api_base_url'] ?? '') }}"
                                   placeholder="https://api.sandbox.dana.id"
                                   class="form-input font-mono text-xs">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="form-label" for="dana_merchant_id">Merchant ID <span class="text-red-500">*</span></label>
                            <input type="text"
                                   name="dana_merchant_id"
                                   id="dana_merchant_id"
                                   value="{{ old('dana_merchant_id', $settings['merchant_id'] ?? '') }}"
                                   required
                                   class="form-input font-mono text-xs">
                        </div>

                        <div>
                            <label class="form-label" for="dana_client_id">Client ID <span class="text-red-500">*</span></label>
                            <input type="text"
                                   name="dana_client_id"
                                   id="dana_client_id"
                                   value="{{ old('dana_client_id', $settings['client_id'] ?? '') }}"
                                   required
                                   class="form-input font-mono text-xs">
                        </div>

                        <div>
                            <label class="form-label" for="dana_client_secret">Client Secret <span class="text-red-500">*</span></label>
                            <input type="password"
                                   name="dana_client_secret"
                                   id="dana_client_secret"
                                   value="{{ old('dana_client_secret', $settings['client_secret'] ?? '') }}"
                                   required
                                   class="form-input font-mono text-xs">
                        </div>
                    </div>

                    <div>
                        <label class="form-label" for="dana_callback_url">Webhook / Callback URL</label>
                        <input type="text"
                               name="dana_callback_url"
                               id="dana_callback_url"
                               value="{{ old('dana_callback_url', $settings['callback_url'] ?? url('/api/courier/dana/callback')) }}"
                               class="form-input font-mono text-xs">
                        <p class="form-help">Endpoint URL yang akan menerima konfirmasi pembayaran dari server DANA.</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label" for="dana_public_key">DANA Public Key</label>
                            <textarea name="dana_public_key"
                                      id="dana_public_key"
                                      rows="3"
                                      class="form-textarea font-mono text-xs"
                                      placeholder="-----BEGIN PUBLIC KEY-----">{{ old('dana_public_key', $settings['public_key'] ?? '') }}</textarea>
                        </div>

                        <div>
                            <label class="form-label" for="dana_private_key">Merchant Private Key</label>
                            <textarea name="dana_private_key"
                                      id="dana_private_key"
                                      rows="3"
                                      class="form-textarea font-mono text-xs"
                                      placeholder="-----BEGIN PRIVATE KEY-----">{{ old('dana_private_key', $settings['private_key'] ?? '') }}</textarea>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-200 flex items-center justify-end gap-2">
                        <a href="{{ route('admin.settings.providers') }}" class="btn btn-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary">
                            <span class="material-symbols-outlined">save</span>
                            Simpan Kredensial DANA
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Uji Coba Koneksi (1 Kolom) --}}
        <div class="space-y-5">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Uji Coba Koneksi</div>
                </div>
                <div class="card-body space-y-3">
                    <p class="text-xs text-slate-500">
                        Membuat URL Deeplink Binding memakai kredensial tersimpan untuk memverifikasi validitas konfigurasi DANA.
                    </p>
                    <button type="button" id="btn-test-dana" class="btn btn-secondary w-full">
                        <span class="material-symbols-outlined">sync_alt</span>
                        Tes Koneksi API DANA
                    </button>
                    <div id="test-result-box" class="hidden rounded-lg p-3 text-xs"></div>
                </div>
            </div>

            <div class="card p-4 space-y-2 text-xs">
                <div class="font-semibold text-slate-900 border-b border-slate-100 pb-2">Catatan Integrasi DANA</div>
                <p class="text-slate-500 leading-relaxed">
                    Untuk mode <strong>Production</strong>, pastikan IP server City Courier telah didaftarkan dalam whitelist pada dashboard DANA Developer Portal.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('btn-test-dana')?.addEventListener('click', function () {
    const btn = this;
    const box = document.getElementById('test-result-box');
    btn.disabled = true;
    btn.classList.add('opacity-50');

    fetch('{{ route("admin.settings.dana.test") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        }
    })
    .then(r => r.json())
    .then(data => {
        box.classList.remove('hidden', 'bg-red-50', 'text-red-700', 'bg-emerald-50', 'text-emerald-700');
        if (data.success) {
            box.classList.add('bg-emerald-50', 'text-emerald-700');
            box.innerHTML = '<strong>' + data.message + '</strong>';
        } else {
            box.classList.add('bg-red-50', 'text-red-700');
            box.innerHTML = '<strong>' + (data.message || 'Koneksi gagal.') + '</strong>';
        }
    })
    .catch(() => {
        box.classList.remove('hidden', 'bg-emerald-50', 'text-emerald-700');
        box.classList.add('bg-red-50', 'text-red-700');
        box.innerHTML = '<strong>Koneksi gagal.</strong> Periksa koneksi server.';
    })
    .finally(() => {
        btn.disabled = false;
        btn.classList.remove('opacity-50');
    });
});
</script>
@endsection
