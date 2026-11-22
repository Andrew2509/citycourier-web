@extends('layouts.admin')

@section('title', 'Pengaturan Gateway, Komisi & Auto-Payout')

@section('content')
@include('admin.finance._nav', ['mode' => $config['mode']])

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <form method="POST" action="{{ route('admin.finance.settings.update') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2 space-y-4">
        @csrf
        <h2 class="text-sm font-semibold text-slate-900">Konfigurasi Operasional Keuangan</h2>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Environment Mode</label>
                <select name="payment_wallet_mode" class="form-select">
                    <option value="demo" @selected($config['mode'] === 'demo')>DEMO (Simulasi Lokal)</option>
                    <option value="sandbox" @selected($config['mode'] === 'sandbox')>SANDBOX (Uji Penyedia)</option>
                    <option value="production" @selected($config['mode'] === 'production')>PRODUCTION (Live)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Payment Provider (Money In)</label>
                <select name="payment_active_provider" class="form-select">
                    <option value="xendit" @selected($config['active_payment_provider'] === 'xendit')>Xendit (Satu-satunya Gateway Aktif)</option>
                    <option value="mock" @selected($config['active_payment_provider'] === 'mock')>MockProvider (Sandbox/Demo Only)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Payout Provider (Money Out)</label>
                <select name="payout_active_provider" class="form-select">
                    <option value="xendit" @selected($config['active_payout_provider'] === 'xendit')>Xendit Payouts v2</option>
                    <option value="mock" @selected($config['active_payout_provider'] === 'mock')>MockProvider (Sandbox/Demo Only)</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Komisi Platform Kurir (%)</label>
                <input type="number" step="0.1" name="courier_commission_rate_percent" value="{{ $config['commission_rate_percent'] }}"
                       class="form-select">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Masa Tunggu Settlement (Menit)</label>
                <input type="number" name="courier_settlement_waiting_minutes" value="{{ $config['settlement_waiting_minutes'] }}"
                       class="form-select">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Minimum Auto-Payout Kurir (IDR)</label>
                <input type="number" name="courier_min_payout_idr" value="{{ $config['min_payout_idr'] }}"
                       class="form-select">
            </div>
        </div>

        <div class="space-y-2 pt-2">
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="courier_auto_payout_enabled" value="1" @checked($config['auto_payout_enabled'])>
                Aktifkan Scheduler Auto-Payout Kurir
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="customer_withdrawal_enabled_in_sandbox" value="1" @checked($config['customer_withdrawal_enabled'])>
                Izinkan Pengujian Customer Withdrawal di Mode Sandbox / Demo
            </label>
        </div>

        <div class="pt-2">
            <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                Simpan Konfigurasi Keuangan
            </button>
        </div>
    </form>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-3">
        <h2 class="text-sm font-semibold text-slate-900">Status Kredensial &amp; Regulasi</h2>
        <div class="text-xs space-y-2">
            <div class="flex justify-between">
                <span>Xendit Credentials</span>
                <span class="font-semibold {{ $config['xendit_configured'] ? 'text-emerald-700' : 'text-amber-700' }}">
                    {{ $config['xendit_configured'] ? 'Terkonfigurasi' : 'Kosong (Sandbox Fallback ke Mock)' }}
                </span>
            </div>
            <div class="flex justify-between">
                <span>Regulatory Approval Stored-Value</span>
                <span class="font-semibold {{ $config['regulatory_approved'] ? 'text-emerald-700' : 'text-red-600' }}">
                    {{ $config['regulatory_approved'] ? 'DISETUJUI' : 'TERKUNCI DI PRODUCTION' }}
                </span>
            </div>
        </div>
        <div class="rounded-lg bg-cream-50 border border-slate-100 p-3 text-xs text-slate-600 space-y-2">
            <div>Secret API keys tersimpan terenkripsi di database (via <a href="{{ route('admin.settings.providers') }}" class="font-semibold text-primary-600 hover:underline">Provider &amp; Integrasi Eksternal</a>) atau dari <code class="font-mono">.env</code> dan tidak pernah dikirim ke Flutter atau HTML.</div>
        </div>
    </div>
</div>
@endsection
