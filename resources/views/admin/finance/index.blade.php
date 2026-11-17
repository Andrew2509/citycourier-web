@extends('layouts.admin')

@section('title', 'Payment & Wallet Overview')

@section('content')
@include('admin.finance._nav', ['mode' => $metrics['mode']])

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="text-xs font-medium text-slate-500">Saldo Customer Wallet</div>
        <div class="mt-1 text-xl font-bold text-slate-900">Rp {{ number_format($metrics['wallets']['customer_available_idr'], 0, ',', '.') }}</div>
        <div class="mt-1 text-xs text-slate-500">Top-Up Lunas: Rp {{ number_format($metrics['flows']['topups_settled_idr'], 0, ',', '.') }}</div>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="text-xs font-medium text-slate-500">Saldo Tersedia Kurir</div>
        <div class="mt-1 text-xl font-bold text-emerald-700">Rp {{ number_format($metrics['wallets']['courier_available_idr'], 0, ',', '.') }}</div>
        <div class="mt-1 text-xs text-slate-500">Pending Settlement: Rp {{ number_format($metrics['wallets']['courier_pending_idr'], 0, ',', '.') }}</div>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="text-xs font-medium text-slate-500">Order Payments &amp; Komisi Platform</div>
        <div class="mt-1 text-xl font-bold text-slate-900">Rp {{ number_format($metrics['flows']['order_payments_idr'], 0, ',', '.') }}</div>
        <div class="mt-1 text-xs text-slate-500">Total Komisi Kurir: Rp {{ number_format($metrics['flows']['platform_commission_idr'], 0, ',', '.') }}</div>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="text-xs font-medium text-slate-500">Status Rekonsiliasi Ledger</div>
        @php $clean = $metrics['reconciliation']['is_clean']; @endphp
        <div class="mt-1 text-xl font-bold {{ $clean ? 'text-emerald-700' : 'text-red-600' }}">
            {{ $clean ? 'SEIMBANG (CLEAN)' : 'PERLU PERHATIAN' }}
        </div>
        <div class="mt-1 text-xs text-slate-500">
            Selisih: Rp {{ number_format($metrics['reconciliation']['imbalance_amount'], 0, ',', '.') }}
            &bull; {{ $metrics['reconciliation']['wallets_checked'] }} wallet
        </div>
    </div>
</div>

<div class="grid grid-cols-1 gap-4 lg:grid-cols-3 mb-6">
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-800 mb-3">Antrean &amp; Peringatan Operasional</h2>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between"><span>Top-up Pending</span><span class="font-semibold">{{ $metrics['flows']['topups_pending_count'] }}</span></div>
            <div class="flex justify-between"><span>Top-up Gagal / Kedaluwarsa</span><span class="font-semibold text-red-600">{{ $metrics['flows']['topups_failed_count'] }}</span></div>
            <div class="flex justify-between"><span>Payout Dalam Proses</span><span class="font-semibold text-amber-600">{{ $metrics['flows']['payouts_processing_count'] }}</span></div>
            <div class="flex justify-between"><span>Payout Gagal / Reversed</span><span class="font-semibold text-red-600">{{ $metrics['flows']['payouts_failed_count'] }}</span></div>
            <div class="flex justify-between"><span>Refund Menunggu Review</span><span class="font-semibold text-blue-600">{{ $metrics['flows']['refunds_pending_count'] }}</span></div>
            <div class="flex justify-between"><span>Wallet Dibekukan Admin</span><span class="font-semibold">{{ $metrics['wallets']['frozen_count'] }}</span></div>
            <div class="flex justify-between"><span>Webhook Gagal / Invalid Sig</span><span class="font-semibold text-red-600">{{ $metrics['flows']['failed_webhooks_count'] }}</span></div>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:col-span-2">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-semibold text-slate-800">Konfigurasi Gateway &amp; Kepatuhan Saat Ini</h2>
            <a href="{{ route('admin.finance.settings') }}" class="text-xs font-semibold text-orange-600 hover:underline">Kelola Pengaturan</a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
            <div class="rounded-lg bg-slate-50 p-3">
                <div class="text-xs text-slate-500">Payment Provider Aktif</div>
                <div class="font-semibold uppercase text-slate-900">{{ $metrics['active_payment_provider'] }}</div>
            </div>
            <div class="rounded-lg bg-slate-50 p-3">
                <div class="text-xs text-slate-500">Payout Provider Aktif</div>
                <div class="font-semibold uppercase text-slate-900">{{ $metrics['active_payout_provider'] }}</div>
            </div>
            <div class="rounded-lg bg-slate-50 p-3">
                <div class="text-xs text-slate-500">Customer Withdrawal Status</div>
                <div class="font-semibold {{ $metrics['customer_withdrawal_enabled'] ? 'text-amber-700' : 'text-slate-700' }}">
                    {{ $metrics['customer_withdrawal_enabled'] ? 'Aktif (Mode Non-Produksi / Disetujui)' : 'DINONAKTIFKAN (Menunggu Persetujuan Regulasi)' }}
                </div>
            </div>
            <div class="rounded-lg bg-slate-50 p-3">
                <div class="text-xs text-slate-500">Total Dana Tertahan (Reserved Payout)</div>
                <div class="font-semibold text-slate-900">Rp {{ number_format($metrics['wallets']['total_reserved_idr'], 0, ',', '.') }}</div>
            </div>
        </div>
    </div>
</div>

<div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
    <div class="flex items-center justify-between mb-3">
        <h2 class="text-sm font-semibold text-slate-800">Jurnal Double-Entry Ledger Terbaru</h2>
        <a href="{{ route('admin.finance.reconciliation') }}" class="text-xs font-semibold text-orange-600 hover:underline">Lihat Rekonsiliasi Lengkap</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="border-b border-slate-200 text-slate-500">
                <tr>
                    <th class="py-2 pr-3">Reference</th>
                    <th class="py-2 pr-3">Wallet</th>
                    <th class="py-2 pr-3">Akun</th>
                    <th class="py-2 pr-3">Posisi</th>
                    <th class="py-2 pr-3 text-right">Nominal (IDR)</th>
                    <th class="py-2 pr-3">Mode</th>
                    <th class="py-2">Waktu</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($metrics['recent_ledger'] as $entry)
                    <tr>
                        <td class="py-2 pr-3 font-mono text-slate-800">{{ $entry->journal_reference }}</td>
                        <td class="py-2 pr-3">{{ $entry->wallet_id ? '#'.$entry->wallet_id : 'SYSTEM' }}</td>
                        <td class="py-2 pr-3 font-mono">{{ $entry->account_code }}</td>
                        <td class="py-2 pr-3">
                            <span class="rounded px-1.5 py-0.5 font-semibold uppercase {{ $entry->entry_type === 'credit' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                                {{ $entry->entry_type }}
                            </span>
                        </td>
                        <td class="py-2 pr-3 text-right font-semibold">Rp {{ number_format($entry->amount, 0, ',', '.') }}</td>
                        <td class="py-2 pr-3 uppercase text-slate-500">{{ $entry->mode }}</td>
                        <td class="py-2 text-slate-500">{{ $entry->created_at?->format('d/m H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-6 text-center text-slate-400">Belum ada jurnal ledger tercatat.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
