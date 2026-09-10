@extends('layouts.admin')

@section('title', 'Manajemen Drop Point')

@section('content')
<div class="flex flex-col w-full gap-space-xl">
    <!-- Breadcrumb & Top Action Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-space-md">
        <div class="flex flex-col gap-space-2xs">
            <div class="flex items-center gap-space-xs text-secondary font-label-md">
                <a class="hover:text-primary transition-colors flex items-center gap-1" href="{{ route('admin.dashboard') }}">
                    <span class="material-symbols-outlined text-[15px]">home</span>
                    <span>Beranda</span>
                </a>
                <span class="text-outline-variant">/</span>
                <span class="text-secondary font-medium">Manajemen</span>
                <span class="text-outline-variant">/</span>
                <span class="text-primary font-bold">Drop Point</span>
            </div>
            <div class="flex items-baseline gap-space-sm mt-1">
                <h1 class="font-headline-xl text-headline-xl text-on-surface font-bold tracking-tight">Manajemen Drop Point</h1>
                <span class="px-space-xs py-space-2xs rounded-full font-label-sm text-label-sm bg-secondary-container text-on-secondary-container font-semibold">{{ $dropPoints->count() }} Hub Aktif</span>
            </div>
            <p class="font-body-md text-body-md text-secondary">Kelola jaringan hub operasional, kantor cabang, dan agen drop point CityCourier.</p>
        </div>
        <div class="flex items-center flex-wrap gap-space-sm">
            <a href="{{ route('admin.drop-points.index') }}" class="inline-flex items-center gap-space-xs px-space-md py-space-xs h-9 bg-surface-container-lowest hover:bg-surface-container-high text-on-surface font-label-md rounded-lg shadow-sm transition-all">
                <span class="material-symbols-outlined text-[18px] text-secondary">refresh</span>
                <span>Refresh</span>
            </a>
            <a href="{{ route('admin.drop-points.create') }}" class="inline-flex items-center gap-space-xs px-space-lg py-space-xs h-9 bg-primary-container hover:bg-primary text-on-primary font-label-md rounded-lg shadow-md transition-all">
                <span class="material-symbols-outlined text-[18px]">add_location_alt</span>
                <span class="font-semibold">+ Tambah Drop Point</span>
            </a>
        </div>
    </div>

    <!-- Operational Stat KPI Cards -->
    @php
        $activeCount = $dropPoints->filter(fn($dp) => $dp->is_active)->count();
        $totalRating = $dropPoints->avg('rating');
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-space-md">
        <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-space-sm">
                <span class="font-label-sm uppercase tracking-wider text-secondary font-bold">Total Drop Point</span>
                <div class="w-8 h-8 rounded-lg bg-surface-container flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined text-[20px]">warehouse</span>
                </div>
            </div>
            <div class="flex items-baseline justify-between">
                <div>
                    <div class="font-display-lg text-display-lg font-bold text-on-surface leading-none">{{ $dropPoints->total() }}</div>
                    <span class="font-label-sm text-label-sm text-secondary font-medium mt-1 inline-block">Lokasi Hub Aktif</span>
                </div>
                <span class="inline-flex items-center gap-0.5 px-space-xs py-space-2xs rounded-full bg-emerald-50 text-emerald-800 font-label-sm font-semibold">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 mr-1 animate-pulse"></span>
                    {{ $dropPoints->total() > 0 ? round(($activeCount / $dropPoints->total()) * 100) : 0 }}% Online
                </span>
            </div>
        </div>
        <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-space-sm">
                <span class="font-label-sm uppercase tracking-wider text-secondary font-bold">Status Aktif</span>
                <div class="w-8 h-8 rounded-lg bg-surface-container flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined text-[20px]">check_circle</span>
                </div>
            </div>
            <div class="flex items-baseline justify-between">
                <div>
                    <div class="font-display-lg text-display-lg font-bold text-on-surface leading-none">{{ $activeCount }}</div>
                    <span class="font-label-sm text-label-sm text-secondary font-medium mt-1 inline-block">Drop Point Beroperasi</span>
                </div>
                <span class="inline-flex items-center px-space-xs py-space-2xs rounded-full bg-surface-container text-on-surface-variant font-label-sm font-semibold">
                    Aktif
                </span>
            </div>
        </div>
        <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-space-sm">
                <span class="font-label-sm uppercase tracking-wider text-secondary font-bold">Rata-rata Rating</span>
                <div class="w-8 h-8 rounded-lg bg-surface-container flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined text-[20px]">star</span>
                </div>
            </div>
            <div class="flex items-baseline justify-between">
                <div>
                    <div class="font-display-lg text-display-lg font-bold text-on-surface leading-none">{{ number_format($totalRating, 1) }}</div>
                    <span class="font-label-sm text-label-sm text-secondary font-medium mt-1 inline-block">/ 5.0 Penilaian</span>
                </div>
                <span class="inline-flex items-center gap-0.5 px-space-xs py-space-2xs rounded-full bg-amber-50 text-amber-800 font-label-sm font-semibold">
                    <span class="material-symbols-outlined text-[14px]">star</span>
                    Bintang
                </span>
            </div>
        </div>
        <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-space-sm">
                <span class="font-label-sm uppercase tracking-wider text-secondary font-bold">Nonaktif</span>
                <div class="w-8 h-8 rounded-lg bg-surface-container flex items-center justify-center text-secondary">
                    <span class="material-symbols-outlined text-[20px]">pause_circle</span>
                </div>
            </div>
            <div class="flex items-baseline justify-between">
                <div>
                    <div class="font-display-lg text-display-lg font-bold text-on-surface leading-none">{{ $dropPoints->total() - $activeCount }}</div>
                    <span class="font-label-sm text-label-sm text-secondary font-medium mt-1 inline-block">Drop Point Nonaktif</span>
                </div>
                <span class="inline-flex items-center px-space-xs py-space-2xs rounded-full bg-surface-container text-on-surface-variant font-label-sm font-semibold">
                    Offline
                </span>
            </div>
        </div>
    </div>

    <!-- Main Workspace Card: Table & Filters -->
    <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden flex flex-col">
        <!-- Panel Header -->
        <div class="p-space-lg flex flex-col lg:flex-row lg:items-center justify-between gap-space-md bg-surface-container-lowest">
            <div class="flex items-center gap-space-sm">
                <div class="w-10 h-10 rounded-lg bg-primary-fixed flex items-center justify-center text-on-primary-fixed-variant">
                    <span class="material-symbols-outlined text-[22px]">location_on</span>
                </div>
                <div class="flex flex-col">
                    <div class="flex items-center gap-space-sm">
                        <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold">Daftar Drop Point</h2>
                        <span class="px-space-xs py-space-2xs rounded-full bg-surface-container text-on-surface font-label-sm font-semibold">Total: {{ $dropPoints->total() }} Lokasi</span>
                    </div>
                    <span class="font-body-sm text-body-sm text-secondary">Semua titik drop barang resmi dan agen kemitraan CityCourier</span>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-space-sm">
                <div class="relative min-w-[280px]">
                    <span class="material-symbols-outlined absolute left-space-sm top-1/2 -translate-y-1/2 text-secondary text-[18px] pointer-events-none">search</span>
                    <input class="w-full bg-surface pl-9 pr-space-md py-space-xs h-9 rounded-lg font-body-sm text-body-sm text-on-surface placeholder:text-secondary focus:outline-none focus:bg-surface-container-lowest shadow-sm" placeholder="Cari nama, alamat, telepon..." type="text" id="dpSearchInput" onkeyup="filterDPTable()"/>
                </div>
            </div>
        </div>

        <!-- Data Table -->
        @if($dropPoints->count() > 0)
        <div class="overflow-x-auto w-full">
            <table class="w-full text-left border-collapse" id="dpTable">
                <thead>
                    <tr class="bg-surface text-secondary font-label-sm uppercase tracking-wider">
                        <th class="py-space-md px-space-lg font-bold">NAMA KANTOR / HUB</th>
                        <th class="py-space-md px-space-lg font-bold">ALAMAT LENGKAP</th>
                        <th class="py-space-md px-space-lg font-bold">TELEPON & PIC</th>
                        <th class="py-space-md px-space-lg font-bold">JAM KERJA</th>
                        <th class="py-space-md px-space-lg font-bold">RATING</th>
                        <th class="py-space-md px-space-lg font-bold text-center">STATUS</th>
                        <th class="py-space-md px-space-lg font-bold text-right">AKSI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-container-high font-body-md text-body-md text-on-surface">
                    @foreach($dropPoints as $dp)
                    @php $isActive = $dp->is_active; @endphp
                    <tr class="hover:bg-surface transition-colors group">
                        <td class="py-space-md px-space-lg">
                            <div class="flex items-center gap-space-md">
                                <div class="w-10 h-10 rounded-lg bg-surface-container flex items-center justify-center text-primary shrink-0 shadow-sm group-hover:scale-105 transition-transform">
                                    <span class="material-symbols-outlined text-[22px]">domain</span>
                                </div>
                                <div class="flex flex-col min-w-0">
                                    <span class="font-headline-sm text-headline-sm font-bold text-on-surface truncate">{{ $dp->name }}</span>
                                    <span class="font-label-sm text-secondary">Drop Point</span>
                                </div>
                            </div>
                        </td>
                        <td class="py-space-md px-space-lg max-w-sm">
                            <p class="font-body-sm text-body-sm text-on-surface line-clamp-2 leading-relaxed">{{ $dp->address ?? '-' }}</p>
                        </td>
                        <td class="py-space-md px-space-lg whitespace-nowrap">
                            <div class="flex flex-col">
                                @if($dp->phone)
                                <a class="font-data-mono text-data-mono text-primary hover:underline font-semibold flex items-center gap-1" href="tel:{{ $dp->phone }}">
                                    <span class="material-symbols-outlined text-[14px]">call</span>
                                    {{ $dp->phone }}
                                </a>
                                @else
                                <span class="font-data-mono text-data-mono text-secondary">-</span>
                                @endif
                            </div>
                        </td>
                        <td class="py-space-md px-space-lg whitespace-nowrap">
                            <div class="flex flex-col">
                                <span class="font-label-md text-label-md font-semibold text-on-surface">{{ $dp->schedule ?? '07:00 - 21:00 WIB' }}</span>
                                <span class="font-label-sm text-label-sm text-secondary">Buka Setiap Hari</span>
                            </div>
                        </td>
                        <td class="py-space-md px-space-lg whitespace-nowrap">
                            <div class="flex items-center gap-space-xs">
                                <div class="flex items-center">
                                    @for($i = 1; $i <= 5; $i++)
                                    <span class="material-symbols-outlined text-[16px] {{ $i > round($dp->rating) ? 'text-gray-300' : 'text-amber-400' }}" {{ $i <= round($dp->rating) ? "style=font-variation-settings:'FILL' 1" : '' }}>star</span>
                                    @endfor
                                </div>
                                <span class="font-data-mono text-data-mono text-on-surface font-semibold">{{ number_format($dp->rating, 1) }}</span>
                            </div>
                        </td>
                        <td class="py-space-md px-space-lg whitespace-nowrap text-center">
                            <span class="inline-flex items-center gap-1.5 px-space-sm py-space-2xs rounded-full {{ $isActive ? 'bg-emerald-50 text-emerald-800' : 'bg-surface-container text-secondary' }} font-label-md font-bold shadow-sm">
                                <span class="w-2 h-2 rounded-full {{ $isActive ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>
                                {{ $isActive ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="py-space-md px-space-lg whitespace-nowrap text-right">
                            <div class="inline-flex items-center gap-space-xs">
                                <form action="{{ route('admin.drop-points.toggle-active', $dp->id) }}" method="POST" class="inline-block">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="p-space-xs text-secondary hover:text-primary hover:bg-surface-container rounded-lg transition-all" title="{{ $isActive ? 'Nonaktifkan' : 'Aktifkan' }}">
                                        <span class="material-symbols-outlined text-[18px]">{{ $isActive ? 'toggle_on' : 'toggle_off' }}</span>
                                    </button>
                                </form>
                                <a href="{{ route('admin.drop-points.edit', $dp->id) }}" class="p-space-xs text-secondary hover:text-primary hover:bg-surface-container rounded-lg transition-all" title="Edit Hub">
                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                </a>
                                <form action="{{ route('admin.drop-points.destroy', $dp->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Apakah Anda yakin ingin menghapus Drop Point ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-space-xs text-secondary hover:text-error hover:bg-error-container/20 rounded-lg transition-all" title="Hapus Drop Point">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <!-- Table Footer -->
        <div class="p-space-md bg-surface flex items-center justify-between text-secondary font-label-sm">
            <span>Menampilkan {{ $dropPoints->count() }} dari {{ $dropPoints->total() }} Drop Point terdaftar</span>
            <div class="flex items-center gap-space-xs">
                {{ $dropPoints->links() }}
            </div>
        </div>
        @else
        <!-- Empty State -->
        <div class="py-space-2xl px-space-md flex flex-col items-center justify-center text-center">
            <div class="w-16 h-16 rounded-full bg-surface-container-high flex items-center justify-center text-secondary mb-space-md shadow-inner">
                <span class="material-symbols-outlined text-[32px] text-secondary">warehouse</span>
            </div>
            <h3 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Belum ada drop point</h3>
            <p class="font-body-sm text-body-sm text-secondary max-w-sm mt-space-xs">Mulai dengan menambahkan drop point baru untuk mendistribusikan paket.</p>
            <a href="{{ route('admin.drop-points.create') }}" class="mt-space-lg inline-flex items-center gap-space-xs px-space-md py-space-xs bg-primary-container hover:bg-primary text-on-primary font-label-md text-label-md font-semibold rounded-lg shadow-sm transition-all">
                <span class="material-symbols-outlined text-[18px]">add</span>
                Tambah Drop Point
            </a>
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
function filterDPTable() {
    const input = document.getElementById('dpSearchInput');
    const filter = input.value.toLowerCase();
    const table = document.getElementById('dpTable');
    if (!table) return;
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    for (let i = 0; i < rows.length; i++) {
        const text = rows[i].textContent.toLowerCase();
        rows[i].style.display = text.indexOf(filter) > -1 ? '' : 'none';
    }
}
</script>
@endpush
@endsection
