@extends('layouts.admin')

@section('title', 'Layanan Pembayaran & Gateway')

@section('content')
<div class="space-y-5">
    {{-- Header Halaman --}}
    <div class="page-header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.settings.providers') }}" class="back-link" title="Kembali ke Daftar Provider">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            </a>
            <div>
                <h1 class="page-title">Layanan Pembayaran &amp; Gateway</h1>
                <p class="page-subtitle">Kelola integrasi gerbang pembayaran virtual account, QRIS, dan dompet digital City Courier.</p>
            </div>
        </div>

        <div class="page-actions">
            <span class="badge {{ ($payment['env'] ?? 'sandbox') === 'production' ? 'badge-green' : 'badge-amber' }}">
                Mode: {{ strtoupper($payment['env'] ?? 'SANDBOX') }}
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {{-- Form Kredensial Payment (2 Kolom) --}}
        <div class="lg:col-span-2 card">
            <div class="card-header">
                <div class="card-title">Kredensial Gateway Pembayaran</div>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.settings.payment.update') }}" method="POST" class="space-y-4">
                    @csrf

                    <div>
                        <label class="form-label" for="komerce_payment_env">Environment Mode <span class="text-red-500">*</span></label>
                        <select name="komerce_payment_env" id="komerce_payment_env" class="form-select">
                            <option value="sandbox" {{ old('komerce_payment_env', $payment['env'] ?? '') === 'sandbox' ? 'selected' : '' }}>Sandbox (Testing)</option>
                            <option value="production" {{ old('komerce_payment_env', $payment['env'] ?? '') === 'production' ? 'selected' : '' }}>Production (Live Transaksi)</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label" for="komerce_payment_api_key">API Secret Key <span class="text-red-500">*</span></label>
                        <input type="password"
                               name="komerce_payment_api_key"
                               id="komerce_payment_api_key"
                               value="{{ old('komerce_payment_api_key', $payment['api_key'] ?? '') }}"
                               required
                               placeholder="kmp_live_... atau kmp_sandbox_..."
                               class="form-input font-mono text-xs">
                        <p class="form-help">Kunci autentikasi rahasia untuk otorisasi pembuatan invoice dan transaksi.</p>
                    </div>

                    <div>
                        <label class="form-label" for="komerce_payment_callback_key">Webhook Callback Signature Key</label>
                        <input type="text"
                               name="komerce_payment_callback_key"
                               id="komerce_payment_callback_key"
                               value="{{ old('komerce_payment_callback_key', $payment['callback_key'] ?? '') }}"
                               placeholder="Signature key untuk verifikasi payload webhook"
                               class="form-input font-mono text-xs">
                        <p class="form-help">Digunakan untuk memvalidasi keamanan notifikasi pembayaran masuk dari gateway.</p>
                    </div>

                    <div class="pt-3 border-t border-slate-200 flex items-center justify-end gap-2">
                        <a href="{{ route('admin.settings.providers') }}" class="btn btn-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary">
                            <span class="material-symbols-outlined">save</span>
                            Simpan Pengaturan Pembayaran
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Uji Coba Payment (1 Kolom) --}}
        <div class="space-y-5">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Uji Koneksi Gateway</div>
                </div>
                <div class="card-body space-y-3">
                    <p class="text-xs text-slate-500">
                        Verifikasi apakah kunci API valid dan dapat terhubung ke endpoint pembayaran.
                    </p>
                    <form action="{{ route('admin.settings.payment.test') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-secondary w-full">
                            <span class="material-symbols-outlined">verified</span>
                            Tes Koneksi Gateway
                        </button>
                    </form>
                </div>
            </div>

            <div class="card p-4 space-y-2 text-xs">
                <div class="font-semibold text-slate-900 border-b border-slate-100 pb-2">Metode Pembayaran Didukung</div>
                <ul class="space-y-1 text-slate-500 list-disc list-inside">
                    <li>QRIS Nasional (Semua Bank &amp; E-Wallet)</li>
                    <li>Virtual Account (BCA, Mandiri, BNI, BRI)</li>
                    <li>Tunai / COD (Serah terima kurir)</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
