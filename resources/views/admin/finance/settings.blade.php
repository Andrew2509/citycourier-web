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
                <select name="payment_wallet_mode" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="demo" @selected($config['mode'] === 'demo')>DEMO (Simulasi Lokal)</option>
                    <option value="sandbox" @selected($config['mode'] === 'sandbox')>SANDBOX (Uji Penyedia)</option>
                    <option value="production" @selected($config['mode'] === 'production')>PRODUCTION (Live)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Payment Provider (Money In)</label>
                <select name="payment_active_provider" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="xendit" @selected($config['active_payment_provider'] === 'xendit')>Xendit (Utama)</option>
                    <option value="doku" @selected($config['active_payment_provider'] === 'doku')>DOKU Jokul (Alternatif)</option>
                    <option value="mock" @selected($config['active_payment_provider'] === 'mock')>MockProvider (Sandbox/Demo Only)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Payout Provider (Money Out)</label>
                <select name="payout_active_provider" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="xendit" @selected($config['active_payout_provider'] === 'xendit')>Xendit Payouts v2</option>
                    <option value="mock" @selected($config['active_payout_provider'] === 'mock')>MockProvider (Sandbox/Demo Only)</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Komisi Platform Kurir (%)</label>
                <input type="number" step="0.1" name="courier_commission_rate_percent" value="{{ $config['commission_rate_percent'] }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Masa Tunggu Settlement (Menit)</label>
                <input type="number" name="courier_settlement_waiting_minutes" value="{{ $config['settlement_waiting_minutes'] }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Minimum Auto-Payout Kurir (IDR)</label>
                <input type="number" name="courier_min_payout_idr" value="{{ $config['min_payout_idr'] }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
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
            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                Simpan Konfigurasi Keuangan
            </button>
        </div>
    </form>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-3">
        <h2 class="text-sm font-semibold text-slate-900">Status Kredensial &amp; Regulasi</h2>
        <div class="text-xs space-y-2">
            <div class="flex justify-between">
                <span>Xendit Secret Key (.env)</span>
                <span class="font-semibold {{ $config['xendit_configured'] ? 'text-emerald-700' : 'text-amber-700' }}">
                    {{ $config['xendit_configured'] ? 'Terkonfigurasi' : 'Kosong (Sandbox Fallback ke Mock)' }}
                </span>
            </div>
            <div class="flex justify-between">
                <span>DOKU Jokul Credentials (.env)</span>
                <span class="font-semibold {{ $config['doku_configured'] ? 'text-emerald-700' : 'text-slate-500' }}">
                    {{ $config['doku_configured'] ? 'Terkonfigurasi' : 'Belum Diisi' }}
                </span>
            </div>
            <div class="flex justify-between">
                <span>Regulatory Approval Stored-Value</span>
                <span class="font-semibold {{ $config['regulatory_approved'] ? 'text-emerald-700' : 'text-red-600' }}">
                    {{ $config['regulatory_approved'] ? 'DISETUJUI' : 'TERKUNCI DI PRODUCTION' }}
                </span>
            </div>
        </div>
        <div class="rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
            Secret API keys hanya dibaca dari environment server (<code class="font-mono">.env</code>) dan tidak pernah dikirim ke aplikasi Flutter atau HTML.
        </div>
    </div>
</div>
@endsection
