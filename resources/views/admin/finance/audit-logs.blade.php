@extends('layouts.admin')

@section('title', 'Wallet Audit Logs')

@section('content')
@include('admin.finance._nav')

<div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
    <h2 class="mb-4 text-sm font-semibold text-slate-900">Audit Log Keuangan &amp; Perubahan Status</h2>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="border-b border-slate-200 text-slate-500">
                <tr>
                    <th class="py-2 pr-3">Waktu</th>
                    <th class="py-2 pr-3">Actor</th>
                    <th class="py-2 pr-3">Wallet</th>
                    <th class="py-2 pr-3">Aksi</th>
                    <th class="py-2 pr-3">Catatan</th>
                    <th class="py-2">Detail Perubahan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($logs as $log)
                    <tr>
                        <td class="py-2.5 pr-3 text-slate-500">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                        <td class="py-2.5 pr-3 uppercase">{{ $log->actor_type }} &bull; {{ $log->actor?->name ?? ($log->actor_user_id ? '#'.$log->actor_user_id : 'SYSTEM') }}</td>
                        <td class="py-2.5 pr-3">{{ $log->wallet_id ? '#'.$log->wallet_id : '-' }}</td>
                        <td class="py-2.5 pr-3 font-mono font-semibold">{{ $log->action }}</td>
                        <td class="py-2.5 pr-3">{{ $log->notes ?? '-' }}</td>
                        <td class="py-2.5 font-mono text-[11px] text-slate-500">{{ $log->new_values ? json_encode($log->new_values) : '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-6 text-center text-slate-400">Belum ada catatan audit log.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
</div>
@endsection
