@extends('layouts.admin')

@section('title', 'Wallet Audit Logs')

@section('content')
@include('admin.finance._nav')

<div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
    <h2 class="mb-4 text-sm font-semibold text-slate-900">Audit Log Keuangan &amp; Perubahan Status</h2>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th">Waktu</th>
                    <th">Actor</th>
                    <th">Wallet</th>
                    <th">Aksi</th>
                    <th">Catatan</th>
                    <th>Detail Perubahan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td text-slate-500">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                        <td uppercase">{{ $log->actor_type }} &bull; {{ $log->actor?->name ?? ($log->actor_user_id ? '#'.$log->actor_user_id : 'SYSTEM') }}</td>
                        <td">{{ $log->wallet_id ? '#'.$log->wallet_id : '-' }}</td>
                        <td font-mono font-semibold">{{ $log->action }}</td>
                        <td">{{ $log->notes ?? '-' }}</td>
                        <td font-mono text-[11px] text-slate-500">{{ $log->new_values ? json_encode($log->new_values) : '-' }}</td>
                    </tr>
                @empty
                    @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
</div>
@endsection
