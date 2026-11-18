@extends('layouts.admin')

@section('title', 'Rekening & E-Wallet Terdaftar')

@section('content')
@include('admin.finance._nav')

<div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
    <h2 class="mb-4 text-sm font-semibold text-slate-900">Daftar Tujuan Payout Terdaftar (Customer &amp; Courier)</h2>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Owner</th>
                    <th>Jenis</th>
                    <th>Kanal</th>
                    <th>Nomor (Masked)</th>
                    <th>Atas Nama</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($destinations as $d)
                    <tr>
                        <td>#{{ $d->id }}</td>
                        <td class="cell-primary uppercase">{{ $d->owner_type }} &bull; {{ $d->user?->name ?? '#'.$d->user_id }}</td>
                        <td class="uppercase">{{ $d->destination_type }}</td>
                        <td class="font-semibold">{{ $d->channel_name }} ({{ $d->channel_code }})</td>
                        <td class="cell-mono">{{ $d->account_number_masked }}</td>
                        <td>{{ $d->account_holder_name }}</td>
                        <td>
                            <div class="flex flex-wrap gap-1">
                                @if ($d->is_default)<span class="badge badge-info">DEFAULT</span>@endif
                                @if ($d->is_verified)<span class="badge badge-green">VERIFIED</span>@endif
                            </div>
                        </td>
                    </tr>
                @empty
                @endforelse
            </tbody>
        </table>
        @if ($destinations->isEmpty())
            <div class="empty-state">
                <span class="cc-icon">account_balance</span>
                <div class="empty-title">Belum ada rekening/e-wallet tujuan</div>
                <div class="empty-text">Rekening pelanggan &amp; kurir terdaftar akan muncul di sini.</div>
            </div>
        @endif
    </div>
    <div class="mt-4">{{ $destinations->links() }}</div>
</div>
@endsection
