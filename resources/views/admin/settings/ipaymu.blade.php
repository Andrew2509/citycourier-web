@extends('layouts.admin')

@section('title', 'Pengaturan iPaymu')

@section('content')
<div class="space-y-5">
    {{-- Header Halaman --}}
    <div class="page-header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.settings.providers') }}" class="back-link" title="Kembali ke Daftar Provider">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            </a>
            <div>
                <h1 class="page-title">Pengaturan iPaymu (Payout &amp; Disbursement)</h1>
                <p class="page-subtitle">Gateway pencairan saldo kurir ke akun DANA. Nilai tersimpan ke database — env hanya fallback.</p>
            </div>
        </div>

        <div class="page-actions">
            <span class="badge {{ ($settings['payout_enabled'] ?? false) ? 'badge-green' : 'badge-amber' }}">
                {{ ($settings['payout_enabled'] ?? false) ? 'Payout Aktif' : 'Payout Belum Aktif' }}
            </span>
            <span class="badge {{ str_contains(($settings['base_url'] ?? ''), 'sandbox') ? 'badge-indigo' : 'badge-green' }}">
                {{ str_contains(($settings['base_url'] ?? ''), 'sandbox') ? 'SANDBOX' : 'PRODUCTION' }}
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {{-- Form Kredensial (2 Kolom) --}}
        <div class="lg:col-span-2 card">
            <div class="card-header">
                <div class="card-title">Kredensial Merchant iPaymu</div>
            </div>
            <div class="card-body">
                <form id="ipaymu-settings-form" action="{{ route('admin.settings.ipaymu.update') }}" method="POST" class="space-y-4">
                    @csrf

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label" for="ipaymu_base_url">API Base URL <span class="text-red-500">*</span></label>
                            <input type="text"
                                   name="ipaymu_base_url"
                                   id="ipaymu_base_url"
                                   value="{{ old('ipaymu_base_url', $settings['base_url'] ?? 'https://sandbox.ipaymu.com') }}"
                                   placeholder="https://sandbox.ipaymu.com"
                                   required
                                   class="form-input font-mono text-xs">
                            <p class="form-help">Sandbox: https://sandbox.ipaymu.com — Production: https://api.ipaymu.com</p>
                        </div>

                        <div>
                            <label class="form-label" for="ipaymu_timeout">Timeout API (detik)</label>
                            <input type="number"
                                   name="ipaymu_timeout"
                                   id="ipaymu_timeout"
                                   value="{{ old('ipaymu_timeout', $settings['timeout'] ?? 30) }}"
                                   min="5" max="120"
                                   class="form-input font-mono text-xs">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label" for="ipaymu_va">Virtual Account (VA) <span class="text-red-500">*</span></label>
                            <input type="text"
                                   name="ipaymu_va"
                                   id="ipaymu_va"
                                   value="{{ old('ipaymu_va', $settings['va'] ?? '') }}"
                                   required
                                   class="form-input font-mono text-xs">
                            <p class="form-help">Merchant identity dipakai pada header &lt;va&gt; setiap request.</p>
                        </div>

                        <div>
                            <label class="form-label" for="ipaymu_merchant_id">Merchant ID <span class="text-slate-400">(opsional)</span></label>
                            <input type="text"
                                   name="ipaymu_merchant_id"
                                   id="ipaymu_merchant_id"
                                   value="{{ old('ipaymu_merchant_id', $settings['merchant_id'] ?? '') }}"
                                   class="form-input font-mono text-xs">
                            <p class="form-help">Kosongkan bila sama dengan VA.</p>
                        </div>
                    </div>

                    <div>
                        <label class="form-label" for="ipaymu_api_key">API Key <span class="text-red-500">*</span></label>
                        <input type="password"
                               name="ipaymu_api_key"
                               id="ipaymu_api_key"
                               value="{{ old('ipaymu_api_key', $settings['api_key'] ?? '') }}"
                               autocomplete="off"
                               required
                               class="form-input font-mono text-xs">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label" for="ipaymu_secret_key">Secret Key <span class="text-slate-400">(opsional)</span></label>
                            <input type="password"
                                   name="ipaymu_secret_key"
                                   id="ipaymu_secret_key"
                                   value="{{ old('ipaymu_secret_key', $settings['secret_key'] ?? '') }}"
                                   autocomplete="off"
                                   class="form-input font-mono text-xs">
                        </div>

                        <div>
                            <label class="form-label" for="ipaymu_webhook_secret">Webhook Secret (X-Callback-Signature)</label>
                            <input type="password"
                                   name="ipaymu_webhook_secret"
                                   id="ipaymu_webhook_secret"
                                   value="{{ old('ipaymu_webhook_secret', $settings['webhook_secret'] ?? '') }}"
                                   autocomplete="off"
                                   class="form-input font-mono text-xs">
                            <p class="form-help">Secret callback resmi iPaymu adalah nomor VA kamu (menu Integration). Bila diisi, nilai ini menggantikan VA sebagai secret HMAC callback.</p>
                        </div>
                    </div>

                    <div>
                        <label class="form-label" for="ipaymu_buyer_email">Email Buyer (fallback)</label>
                        <input type="email"
                               name="ipaymu_buyer_email"
                               id="ipaymu_buyer_email"
                               value="{{ old('ipaymu_buyer_email', $settings['buyer_email'] ?? '') }}"
                               placeholder="cs@domain-anda.com"
                               class="form-input font-mono text-xs">
                        <p class="form-help">
                            Email NYATA milik merchant. Dipakai bila email pembeli tidak layak (kosong, domain tanpa
                            mail server, atau alamat hasil generate) supaya iPaymu tidak menolak transaksi dengan
                            pesan "Suspicious buyer".
                        </p>
                    </div>

                    <div class="rounded-lg bg-slate-50 border border-slate-200 p-3">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox"
                                   name="ipaymu_payout_enabled"
                                   value="1"
                                   {{ old('ipaymu_payout_enabled', ($settings['payout_enabled'] ?? false)) ? 'checked' : '' }}
                                   class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                            <div class="text-xs">
                                <span class="font-semibold text-slate-700">Aktifkan Payout/Disbursement</span>
                                <p class="text-slate-500 leading-relaxed">
                                    Centang HANYA setelah iPaymu mengonfirmasi layanan payout sudah aktif untuk merchant kamu.
                                    Bila tidak dicentang, penarikan kurir tetap diproses ke status "Diproses" dengan dana disimpan — tanpa simulasi sukses.
                                </p>
                            </div>
                        </label>
                    </div>

                    <div class="pt-3 border-t border-slate-200 flex items-center justify-end gap-2">
                        <a href="{{ route('admin.settings.providers') }}" class="btn btn-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary">
                            <span class="material-symbols-outlined">save</span>
                            Simpan Kredensial iPaymu
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Panel samping --}}
        <div class="space-y-5">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Uji Coba Koneksi</div>
                </div>
                <div class="card-body space-y-3">
                    <p class="text-xs text-slate-500">
                        Menguji inquiry memakai nilai form saat ini — bisa dijalankan SEBELUM menekan Simpan.
                    </p>
                    <button type="button" id="btn-test-ipaymu" class="btn btn-secondary w-full">
                        <span class="material-symbols-outlined">sync_alt</span>
                        Tes Koneksi API iPaymu
                    </button>
                    <div id="ipaymu-test-result-box" class="hidden rounded-lg p-3 text-xs"></div>
                </div>
            </div>

            <div class="card p-4 space-y-2 text-xs">
                <div class="font-semibold text-slate-900 border-b border-slate-100 pb-2">Webhook Callback</div>
                <div class="flex items-center gap-2">
                    <input type="text" readonly value="{{ $settings['webhook_url'] ?? '' }}" id="ipaymu-webhook-url"
                           class="form-input font-mono text-[10px] bg-slate-50 text-slate-500">
                    <button type="button" onclick="navigator.clipboard?.writeText(document.getElementById('ipaymu-webhook-url').value); this.textContent='Tersalin'"
                            class="btn btn-sm btn-secondary shrink-0">Salin</button>
                </div>
                <p class="text-slate-500 leading-relaxed">
                    Daftarkan URL di atas pada dashboard merchant iPaymu (menu Callback/Notification). Server akan
                    memverifikasi header <span class="font-mono">X-Callback-Signature</span> sebelum memproses.
                </p>
            </div>

            <div class="card p-4 space-y-2 text-xs">
                <div class="font-semibold text-slate-900 border-b border-slate-100 pb-2">Catatan Integrasi iPaymu</div>
                <p class="text-slate-500 leading-relaxed">
                    Payout/disbursement iPaymu butuh <strong>aktivasi merchant</strong>. Aktivasi ditandai dengan
                    centang "Aktifkan Payout" di atas setelah dashboard iPaymu menunjukkan layanan aktif. Status akhir
                    penarikan hanya berubah ke "Berhasil" berdasarkan konfirmasi resmi iPaymu (inquiry/webhook).
                </p>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('btn-test-ipaymu')?.addEventListener('click', function () {
    const btn = this;
    const box = document.getElementById('ipaymu-test-result-box');
    const form = document.getElementById('ipaymu-settings-form');
    const data = new FormData(form);
    data.append('ipaymu_payout_enabled', form.ipaymu_payout_enabled?.checked ? '1' : '0');

    btn.disabled = true;
    btn.classList.add('opacity-50');
    box.classList.remove('hidden', 'bg-emerald-50', 'text-emerald-700', 'bg-red-50', 'text-red-700', 'bg-amber-50', 'text-amber-700');
    box.innerHTML = '<span class="animate-pulse">Menguji koneksi…</span>';

    fetch('{{ route("admin.settings.ipaymu.test") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: data,
    })
    .then(r => r.json().then(j => ({ ok: r.ok, json: j })).catch(() => ({ ok: r.ok, json: { success: false, message: 'Respons tidak valid.' } })))
    .then(({ ok, json }) => {
        if (json.success) {
            box.classList.add('bg-emerald-50', 'text-emerald-700');
        } else {
            box.classList.add(ok ? 'bg-amber-50' : 'bg-red-50', 'text-red-700');
        }
        box.innerHTML = '<strong>' + (json.message || 'Koneksi gagal.') + '</strong>';
    })
    .catch(() => {
        box.classList.remove('hidden', 'bg-emerald-50', 'text-emerald-700', 'bg-amber-50', 'text-amber-700');
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
