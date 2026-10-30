@extends('layouts.admin')

@section('title', 'Pengaturan Provider OVO')

@section('content')
<div class="space-y-5">
    {{-- Header Halaman --}}
    <div class="page-header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.settings.providers') }}" class="back-link" title="Kembali ke Daftar Provider">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            </a>
            <div>
                <h1 class="page-title">Pengaturan Provider OVO SNAP</h1>
                <p class="page-subtitle">Konfigurasi integrasi gateway OVO SNAP B2B untuk pencairan saldo dompet digital kurir mitra.</p>
            </div>
        </div>

        <div class="page-actions">
            <span class="badge {{ ($settings['mode'] ?? 'mock') === 'production' ? 'badge-green' : 'badge-amber' }}">
                Mode: {{ strtoupper($settings['mode'] ?? 'MOCK') }}
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {{-- Form Kredensial --}}
        <div class="lg:col-span-2 card">
            <div class="card-header">
                <div class="card-title">Kredensial OVO SNAP B2B &amp; Disbursement</div>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.settings.ovo.update') }}" method="POST" class="space-y-4">
                    @csrf

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label" for="ovo_mode">Environment Mode <span class="text-red-500">*</span></label>
                            <select name="ovo_mode" id="ovo_mode" class="form-select">
                                <option value="mock" {{ old('ovo_mode', $settings['mode'] ?? '') === 'mock' ? 'selected' : '' }}>Mock (Simulasi Lokal / Testing)</option>
                                <option value="sandbox" {{ old('ovo_mode', $settings['mode'] ?? '') === 'sandbox' ? 'selected' : '' }}>Sandbox (Staging OVO)</option>
                                <option value="production" {{ old('ovo_mode', $settings['mode'] ?? '') === 'production' ? 'selected' : '' }}>Production (Live)</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label" for="ovo_api_base_url">API Base URL</label>
                            <input type="text"
                                   name="ovo_api_base_url"
                                   id="ovo_api_base_url"
                                   value="{{ old('ovo_api_base_url', $settings['api_base_url'] ?? 'https://api.ovo.id') }}"
                                   placeholder="https://api.ovo.id"
                                   class="form-input font-mono text-xs">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label" for="ovo_client_id">Client Key / Client ID</label>
                            <input type="text"
                                   name="ovo_client_id"
                                   id="ovo_client_id"
                                   value="{{ old('ovo_client_id', $settings['client_id'] ?? '') }}"
                                   placeholder="Client Key dari OVO"
                                   class="form-input font-mono text-xs">
                        </div>

                        <div>
                            <label class="form-label" for="ovo_client_secret">Client Secret</label>
                            <input type="password"
                                   name="ovo_client_secret"
                                   id="ovo_client_secret"
                                   value="{{ old('ovo_client_secret', $settings['client_secret'] ?? '') }}"
                                   placeholder="Client Secret OVO"
                                   class="form-input font-mono text-xs">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label" for="ovo_partner_id">Partner ID</label>
                            <input type="text"
                                   name="ovo_partner_id"
                                   id="ovo_partner_id"
                                   value="{{ old('ovo_partner_id', $settings['partner_id'] ?? '') }}"
                                   placeholder="Partner ID OVO"
                                   class="form-input font-mono text-xs">
                        </div>

                        <div>
                            <label class="form-label" for="ovo_merchant_id">Merchant ID</label>
                            <input type="text"
                                   name="ovo_merchant_id"
                                   id="ovo_merchant_id"
                                   value="{{ old('ovo_merchant_id', $settings['merchant_id'] ?? '') }}"
                                   placeholder="Merchant ID OVO"
                                   class="form-input font-mono text-xs">
                        </div>
                    </div>

                    <div>
                        <label class="form-label" for="ovo_private_key">Private Key (RSA PKCS#8)</label>
                        <textarea name="ovo_private_key"
                                  id="ovo_private_key"
                                  rows="4"
                                  placeholder="-----BEGIN RSA PRIVATE KEY-----&#10;...&#10;-----END RSA PRIVATE KEY-----"
                                  class="form-input font-mono text-xs">{{ old('ovo_private_key', $settings['private_key'] ?? '') }}</textarea>
                    </div>

                    <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                        <button type="button" id="btn-test-ovo" class="btn btn-secondary text-xs">
                            <span class="material-symbols-outlined text-[16px]">network_check</span>
                            <span>Uji Koneksi &amp; Saldo OVO</span>
                        </button>

                        <button type="submit" class="btn btn-primary text-xs">
                            <span class="material-symbols-outlined text-[16px]">save</span>
                            <span>Simpan Konfigurasi OVO</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Panel Status & Bantuan --}}
        <div class="space-y-4">
            <div class="card p-4">
                <h3 class="text-xs font-bold text-slate-900 mb-2 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-purple-600 text-[18px]">account_balance_wallet</span>
                    <span>Status Integrasi OVO SNAP</span>
                </h3>
                <p class="text-xs text-slate-600 mb-3 leading-relaxed">
                    Integrasi resmi SNAP B2B OVO menggunakan verifikasi nomor akun real-time via <em>Account Inquiry</em> dan pencairan via <em>Customer Topup</em>.
                </p>
                <div id="test-result-box" class="hidden rounded-lg p-3 text-xs"></div>
            </div>

            <div class="card p-4 bg-purple-50/50 border-purple-100">
                <h3 class="text-xs font-bold text-purple-900 mb-1">Webhook URL OVO</h3>
                <p class="text-[11px] text-purple-700 mb-2">Daftarkan URL ini pada portal pengembang OVO:</p>
                <code class="block bg-white p-2 rounded border border-purple-200 text-[11px] font-mono text-purple-900 select-all">
                    {{ url('/api/ovo/webhook') }}
                </code>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('btn-test-ovo')?.addEventListener('click', function() {
    const btn = this;
    const box = document.getElementById('test-result-box');
    btn.disabled = true;
    btn.classList.add('opacity-50');

    fetch('{{ route("settings.ovo.test") }}', {
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
            box.innerHTML = '<strong>' + data.message + '</strong>';
        }
    })
    .catch(err => {
        box.classList.remove('hidden');
        box.classList.add('bg-red-50', 'text-red-700');
        box.innerHTML = '<strong>Terjadi kesalahan saat menguji koneksi: ' + err.message + '</strong>';
    })
    .finally(() => {
        btn.disabled = false;
        btn.classList.remove('opacity-50');
    });
});
</script>
@endsection

