@extends('layouts.admin')

@section('title', 'Rekening & E-Wallet Terdaftar')

@section('content')
@include('admin.finance._nav')

<div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
    <h2 class="mb-4 text-sm font-semibold text-slate-900">Daftar Tujuan Payout Terdaftar (Customer &amp; Courier)</h2>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="border-b border-slate-200 text-slate-500">
                <tr>
                    <th class="py-2 pr-3">ID</th>
                    <th class="py-2 pr-3">Owner</th>
                    <th class="py-2 pr-3">Jenis</th>
                    <th class="py-2 pr-3">Kanal</th>
                    <th class="py-2 pr-3">Nomor (Masked)</th>
                    <th class="py-2 pr-3">Atas Nama</th>
                    <th class="py-2">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($destinations as $d)
                    <tr>
                        <td class="py-2.5 pr-3">#{{ $d->id }}</td>
                        <td class="py-2.5 pr-3 uppercase">{{ $d->owner_type }} &bull; {{ $d->user?->name ?? '#'.$d->user_id }}</td>
                        <td class="py-2.5 pr-3 uppercase">{{ $d->destination_type }}</td>
                        <td class="py-2.5 pr-3 font-semibold">{{ $d->channel_name }} ({{ $d->channel_code }})</td>
                        <td class="py-2.5 pr-3 font-mono">{{ $d->account_number_masked }}</td>
                        <td class="py-2.5 pr-3">{{ $d->account_holder_name }}</td>
                        <td class="py-2.5">
                            @if ($d->is_default)<span class="rounded bg-blue-100 px-1.5 py-0.5 text-[10px] font-semibold text-blue-700">DEFAULT</span>@endif
                            @if ($d->is_verified)<span class="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700">VERIFIED</span>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-6 text-center text-slate-400">Belum ada rekening/e-wallet tujuan terdaftar.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $destinations->links() }}</div>
</div>
@endsection
