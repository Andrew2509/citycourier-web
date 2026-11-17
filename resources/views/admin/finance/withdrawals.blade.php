@extends('layouts.admin')

@section('title', 'Monitor Customer Withdrawals')

@section('content')
@include('admin.finance._nav', ['mode' => $mode])

@if (! $withdrawalEnabled)
    <div class="mb-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
        <strong>Kebijakan Regulasi Aktif:</strong> Penarikan dana wallet pelanggan dinonaktifkan di mode production hingga struktur entitas hukum dan persetujuan penyedia pembayaran terpenuhi.
    </div>
@endif

<div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
    <h2 class="mb-4 text-sm font-semibold text-slate-900">Riwayat Penarikan Dana Pelanggan</h2>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="border-b border-slate-200 text-slate-500">
                <tr>
                    <th class="py-2 pr-3">Reference</th>
                    <th class="py-2 pr-3">Pelanggan</th>
                    <th class="py-2 pr-3">Tujuan</th>
                    <th class="py-2 pr-3">Mode</th>
                    <th class="py-2 pr-3 text-right">Nominal (IDR)</th>
                    <th class="py-2">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($withdrawals as $w)
                    <tr>
                        <td class="py-2.5 pr-3 font-mono">{{ $w->reference }}</td>
                        <td class="py-2.5 pr-3">{{ $w->user?->name ?? '#'.$w->user_id }}</td>
                        <td class="py-2.5 pr-3">{{ $w->channel_code }} &bull; {{ $w->account_number_masked }}</td>
                        <td class="py-2.5 pr-3 uppercase">{{ $w->mode }}</td>
                        <td class="py-2.5 pr-3 text-right font-semibold">Rp {{ number_format($w->amount, 0, ',', '.') }}</td>
                        <td class="py-2.5 uppercase font-semibold">{{ $w->status }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-6 text-center text-slate-400">Belum ada permintaan withdrawal pelanggan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $withdrawals->links() }}</div>
</div>
@endsection
