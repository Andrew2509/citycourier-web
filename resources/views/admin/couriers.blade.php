@extends('layouts.admin')

@section('content')
<div class="flex flex-col w-full gap-space-xl">
    <!-- Page Header & Action Bar -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-space-md">
        <div class="flex flex-col gap-space-2xs">
            <div class="flex items-center gap-space-xs">
                <span class="font-label-sm text-label-sm uppercase tracking-wider text-primary font-bold">Mitra Lapangan</span>
                <span class="text-secondary font-bold text-[11px]">•</span>
                <span class="font-label-sm text-label-sm text-secondary">Fleet Operations Hub</span>
            </div>
            <h1 class="font-headline-xl text-headline-xl text-on-surface font-bold tracking-tight">Manajemen Armada Kurir</h1>
            <p class="font-body-md text-body-md text-secondary">Kelola data mitra kurir, status verifikasi dokumen, kendaraan, dan performa lapangan.</p>
        </div>
        <div class="flex items-center gap-space-sm self-start md:self-auto">
            <a href="{{ route('admin.couriers') }}" class="h-9 px-space-md rounded-lg bg-surface-container-lowest text-on-surface hover:bg-surface-container shadow-sm flex items-center gap-space-xs font-label-md text-label-md transition-colors">
                <span class="material-symbols-outlined text-[18px] text-secondary">file_download</span>
                <span>Export Data Kurir</span>
            </a>
        </div>
    </div>

    <!-- Operational Stat Cards -->
    @php
        $allCount = $couriers->total();
        $unverifiedCount = $couriers->getCollection()->where('is_verified', false)->count();
        $verifiedCount = $couriers->getCollection()->where('is_verified', true)->count();
        $activeCount = $couriers->getCollection()->where('is_active', true)->count();
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-space-md">
        <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Total Mitra Terdaftar</span>
                <span class="p-space-xs rounded-lg bg-surface-container-high text-primary flex items-center justify-center">
                    <span class="material-symbols-outlined text-[20px]">two_wheeler</span>
                </span>
            </div>
            <div class="flex items-baseline gap-space-xs mt-space-sm">
                <span class="font-display-lg text-display-lg text-on-surface font-bold">{{ $allCount }}</span>
                <span class="font-label-sm text-label-sm text-secondary">Kurir Terdata</span>
            </div>
        </div>
        <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Kurir Siap Kerja / Online</span>
                <span class="p-space-xs rounded-lg bg-secondary-container text-on-secondary-container flex items-center justify-center">
                    <span class="material-symbols-outlined text-[20px]">online_prediction</span>
                </span>
            </div>
            <div class="flex items-baseline gap-space-xs mt-space-sm">
                <span class="font-display-lg text-display-lg text-on-surface font-bold">{{ $activeCount }}</span>
                <span class="font-label-sm text-label-sm text-secondary">Aktif Bertugas</span>
            </div>
            <div class="flex items-center gap-space-xs mt-space-xs">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="font-label-sm text-label-sm text-secondary font-semibold">{{ $allCount > 0 ? round(($activeCount / $allCount) * 100) : 0 }}% Rasio Kesiapan Armada</span>
            </div>
        </div>
        <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Menunggu Verifikasi</span>
                <span class="p-space-xs rounded-lg bg-primary-fixed text-on-primary-fixed-variant flex items-center justify-center">
                    <span class="material-symbols-outlined text-[20px]">pending_actions</span>
                </span>
            </div>
            <div class="flex items-baseline gap-space-xs mt-space-sm">
                <span class="font-display-lg text-display-lg text-on-surface font-bold">{{ $unverifiedCount }}</span>
                <span class="font-label-sm text-label-sm text-secondary">Antrian Berkas</span>
            </div>
            <div class="flex items-center gap-space-xs mt-space-xs">
                <span class="material-symbols-outlined text-[14px] text-secondary font-bold">check_circle</span>
                <span class="font-label-sm text-label-sm text-secondary font-semibold">{{ $unverifiedCount === 0 ? 'Semua pengajuan telah diproses' : 'Perlu perhatian' }}</span>
            </div>
        </div>
        <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Rata-rata Rating Kurir</span>
                <span class="p-space-xs rounded-lg bg-surface-container-high text-primary flex items-center justify-center">
                    <span class="material-symbols-outlined text-[20px]">hotel_class</span>
                </span>
            </div>
            <div class="flex items-baseline gap-space-xs mt-space-sm">
                <span class="font-display-lg text-display-lg text-on-surface font-bold">4.9</span>
                <span class="font-label-sm text-label-sm text-secondary">/ 5.0 (Bintang)</span>
            </div>
            <div class="flex items-center gap-space-xs mt-space-xs">
                <span class="material-symbols-outlined text-[14px] text-primary">sentiment_satisfied</span>
                <span class="font-label-sm text-label-sm text-secondary font-semibold">Kepuasan Pelanggan Prima</span>
            </div>
        </div>
    </div>

    <!-- Filter Toolbar & Tabs -->
    <div class="bg-surface-container-lowest rounded-xl shadow-sm p-space-md flex flex-col lg:flex-row lg:items-center lg:justify-between gap-space-md">
        <!-- Status Filter Pills -->
        <div class="flex items-center gap-space-2xs bg-surface p-space-2xs rounded-lg overflow-x-auto" id="kurirFilterTabs">
            <a href="{{ route('admin.couriers') }}"
               class="tab-btn px-space-md py-space-xs rounded-lg font-label-md text-label-md font-medium transition-colors whitespace-nowrap {{ !request('filter') ? 'bg-primary-container text-on-primary shadow-sm font-bold' : 'text-secondary hover:text-on-surface hover:bg-surface-container-high' }}">
                Semua
            </a>
            <a href="{{ route('admin.couriers', ['filter' => 'verified']) }}"
               class="tab-btn px-space-md py-space-xs rounded-lg font-label-md text-label-md font-medium transition-colors whitespace-nowrap flex items-center gap-space-xs {{ request('filter') === 'verified' ? 'bg-primary-container text-on-primary shadow-sm font-bold' : 'text-secondary hover:text-on-surface hover:bg-surface-container-high' }}">
                <span>Terverifikasi</span>
                <span class="px-1.5 py-0.2 rounded font-data-mono text-[11px] {{ request('filter') === 'verified' ? 'bg-on-primary/20' : 'bg-surface-container-highest text-secondary' }}">{{ $verifiedCount }}</span>
            </a>
            <a href="{{ route('admin.couriers', ['filter' => 'unverified']) }}"
               class="tab-btn px-space-md py-space-xs rounded-lg font-label-md text-label-md font-medium transition-colors whitespace-nowrap flex items-center gap-space-xs {{ request('filter') === 'unverified' ? 'bg-primary-container text-on-primary shadow-sm font-bold' : 'text-secondary hover:text-on-surface hover:bg-surface-container-high' }}">
                <span>Belum Verifikasi</span>
                <span class="px-1.5 py-0.2 rounded font-data-mono text-[11px] {{ request('filter') === 'unverified' ? 'bg-on-primary/20' : 'bg-surface-container-highest text-secondary' }}">{{ $unverifiedCount }}</span>
            </a>
            <a href="{{ route('admin.couriers', ['filter' => 'active']) }}"
               class="tab-btn px-space-md py-space-xs rounded-lg font-label-md text-label-md font-medium transition-colors whitespace-nowrap flex items-center gap-space-xs {{ request('filter') === 'active' ? 'bg-primary-container text-on-primary shadow-sm font-bold' : 'text-secondary hover:text-on-surface hover:bg-surface-container-high' }}">
                <span>Aktif Online</span>
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
            </a>
        </div>
        <!-- Live Search & Control -->
        <div class="flex items-center gap-space-sm flex-1 lg:max-w-md">
            <form method="GET" action="{{ route('admin.couriers') }}" class="relative w-full flex items-center">
                @if(request('filter'))
                    <input type="hidden" name="filter" value="{{ request('filter') }}">
                @endif
                <span class="material-symbols-outlined absolute left-space-md text-secondary pointer-events-none text-[18px]">search</span>
                <input type="text"
                       name="search"
                       value="{{ request('search') }}"
                       placeholder="Cari nama, email, nomor telepon, atau plat kendaraan..."
                       class="w-full bg-surface pl-9 pr-space-md py-space-xs h-9 rounded-lg font-body-sm text-body-sm text-on-surface placeholder:text-secondary focus:outline-none focus:bg-surface-container-lowest focus:ring-1 focus:ring-primary-container shadow-sm transition-all" />
            </form>
            <a href="{{ route('admin.couriers') }}" class="p-space-xs h-9 w-9 rounded-lg bg-surface hover:bg-surface-container text-secondary hover:text-on-surface flex items-center justify-center transition-colors" title="Refresh data">
                <span class="material-symbols-outlined text-[18px]">refresh</span>
            </a>
        </div>
    </div>

    <!-- Primary Content Area: Table -->
    <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden flex flex-col">
        <!-- Section Header -->
        <div class="px-space-lg py-space-md bg-surface-container-lowest flex items-center justify-between border-b border-surface-container-high">
            <div class="flex items-center gap-space-sm">
                <div class="w-9 h-9 rounded-lg bg-primary-fixed flex items-center justify-center text-on-primary-fixed-variant">
                    <span class="material-symbols-outlined text-[20px]">two_wheeler</span>
                </div>
                <div class="flex flex-col">
                    <span class="font-headline-sm text-headline-sm text-on-surface font-bold leading-tight">Daftar Kurir</span>
                    <span class="font-label-sm text-label-sm text-secondary">Menampilkan {{ $couriers->count() }} kurir terverifikasi & aktif</span>
                </div>
            </div>
        </div>

        <!-- High-Density Courier Table -->
        @if($couriers->count() > 0)
        <div class="overflow-x-auto w-full">
            <table class="w-full text-left" id="courierTable">
                <thead>
                    <tr class="bg-surface text-secondary font-label-sm text-label-sm uppercase tracking-wider">
                        <th class="py-space-sm px-space-lg font-semibold" scope="col">Kurir</th>
                        <th class="py-space-sm px-space-md font-semibold" scope="col">Telepon / WhatsApp</th>
                        <th class="py-space-sm px-space-md font-semibold" scope="col">Kendaraan</th>
                        <th class="py-space-sm px-space-md font-semibold" scope="col">Plat Nomor</th>
                        <th class="py-space-sm px-space-md font-semibold" scope="col">Verifikasi</th>
                        <th class="py-space-sm px-space-md font-semibold" scope="col">Status Kerja</th>
                        <th class="py-space-sm px-space-lg font-semibold text-right" scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-container font-body-sm text-body-sm text-on-surface">
                    @foreach($couriers as $courier)
                    <tr class="hover:bg-surface-container-low transition-colors group">
                        <!-- Courier Profile -->
                        <td class="py-space-md px-space-lg">
                            <div class="flex items-center gap-space-sm">
                                <div class="w-10 h-10 rounded-full bg-primary-container text-on-primary flex items-center justify-center font-headline-sm text-headline-sm font-bold shadow-sm shrink-0">
                                    {{ strtoupper(substr($courier->user->name, 0, 1)) }}
                                </div>
                                <div class="flex flex-col min-w-0">
                                    <span class="font-headline-sm text-headline-sm text-on-surface font-semibold truncate">{{ $courier->user->name }}</span>
                                    <span class="font-body-sm text-body-sm text-secondary truncate font-data-mono">{{ $courier->user->email }}</span>
                                    <span class="font-label-sm text-label-sm text-secondary">Bergabung: {{ $courier->created_at ? $courier->created_at->format('d M Y') : '-' }}</span>
                                </div>
                            </div>
                        </td>
                        <!-- Phone / Quick WhatsApp -->
                        <td class="py-space-md px-space-md">
                            <div class="flex items-center gap-space-xs">
                                <span class="font-data-mono text-data-mono text-on-surface font-medium">{{ $courier->phone ?? $courier->user->phone ?? '-' }}</span>
                                @if($courier->phone || $courier->user->phone)
                                <a class="p-1 rounded text-emerald-600 hover:bg-emerald-50 transition-colors flex items-center justify-center" href="https://wa.me/62{{ ltrim($courier->phone ?? $courier->user->phone, '0') }}" target="_blank" title="Kirim Pesan WhatsApp">
                                    <span class="material-symbols-outlined text-[16px]">chat</span>
                                </a>
                                @endif
                            </div>
                        </td>
                        <!-- Vehicle Badge -->
                        <td class="py-space-md px-space-md">
                            <div class="inline-flex items-center gap-space-2xs px-space-sm py-0.5 rounded-lg bg-secondary-fixed text-on-secondary-fixed font-label-md text-label-md font-medium">
                                <span class="material-symbols-outlined text-[16px]">two_wheeler</span>
                                <span>{{ $courier->vehicle_type ?? '-' }}</span>
                            </div>
                        </td>
                        <!-- Vehicle License Plate -->
                        <td class="py-space-md px-space-md">
                            <div class="inline-flex items-center px-space-sm py-0.5 rounded bg-surface-container-high text-on-surface font-data-mono text-data-mono font-bold tracking-wider uppercase">
                                {{ $courier->vehicle_plate ?? '-' }}
                            </div>
                        </td>
                        <!-- Verification Badge -->
                        <td class="py-space-md px-space-md">
                            @if($courier->is_verified)
                            <span class="inline-flex items-center gap-space-2xs px-space-sm py-0.5 rounded-lg bg-emerald-50 text-emerald-700 font-label-md text-label-md font-semibold">
                                <span class="material-symbols-outlined text-[15px] font-bold">check_circle</span>
                                <span>Terverifikasi</span>
                            </span>
                            @else
                            <span class="inline-flex items-center gap-space-2xs px-space-sm py-0.5 rounded-lg bg-amber-50 text-amber-700 font-label-md text-label-md font-semibold">
                                <span class="material-symbols-outlined text-[15px] font-bold">pending</span>
                                <span>Belum Verifikasi</span>
                            </span>
                            @endif
                        </td>
                        <!-- Online/Duty Status -->
                        <td class="py-space-md px-space-md">
                            @if($courier->is_active)
                            <div class="inline-flex items-center gap-space-2xs px-space-sm py-0.5 rounded-lg bg-emerald-50 text-emerald-800 font-label-md text-label-md font-semibold">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                                <span>Aktif</span>
                            </div>
                            @else
                            <div class="inline-flex items-center gap-space-2xs px-space-sm py-0.5 rounded-lg bg-surface-container text-secondary font-label-md text-label-md font-semibold">
                                <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                                <span>Offline</span>
                            </div>
                            @endif
                        </td>
                        <!-- Quick Actions -->
                        <td class="py-space-md px-space-lg text-right">
                            <div class="inline-flex items-center justify-end gap-space-2xs">
                                <form action="{{ route('admin.couriers.verify', $courier->id) }}" method="POST" class="inline-block">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="p-space-xs rounded-lg {{ $courier->is_verified ? 'text-secondary hover:text-error hover:bg-error-container/20' : 'text-secondary hover:text-emerald-600 hover:bg-emerald-50' }} transition-colors" title="{{ $courier->is_verified ? 'Batalkan Verifikasi' : 'Verifikasi Kurir' }}">
                                        <span class="material-symbols-outlined text-[18px]">{{ $courier->is_verified ? 'cancel' : 'verified' }}</span>
                                    </button>
                                </form>
                                <form action="{{ route('admin.couriers.toggle-active', $courier->id) }}" method="POST" class="inline-block">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="p-space-xs rounded-lg {{ $courier->is_active ? 'text-secondary hover:text-error hover:bg-error-container/20' : 'text-secondary hover:text-primary hover:bg-surface-container' }} transition-colors" title="{{ $courier->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                        <span class="material-symbols-outlined text-[18px]">{{ $courier->is_active ? 'block' : 'play_arrow' }}</span>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Table Footer / Pagination -->
        <div class="p-space-md bg-surface flex flex-col sm:flex-row items-center justify-between gap-space-sm">
            <span class="font-label-sm text-label-sm text-secondary">Menampilkan {{ $couriers->firstItem() ?? 0 }} dari {{ $couriers->total() }} total kurir</span>
            <div class="flex items-center gap-space-xs">
                {{ $couriers->withQueryString()->links('pagination::tailwind') }}
            </div>
        </div>
        @else
        <!-- Empty State -->
        <div class="py-space-2xl px-space-lg flex flex-col items-center justify-center text-center max-w-lg mx-auto">
            <div class="w-16 h-16 rounded-full bg-surface-container-high flex items-center justify-center text-secondary mb-space-md shadow-inner">
                <span class="material-symbols-outlined text-[32px] text-secondary">group_off</span>
            </div>
            <h3 class="font-headline-lg text-headline-lg text-on-surface font-bold">Belum ada kurir</h3>
            <p class="font-body-md text-body-md text-secondary mt-space-xs leading-relaxed">
                @if(request('search'))
                    Tidak ada hasil untuk pencarian "{{ request('search') }}".
                @else
                    Kurir yang mendaftar dari aplikasi CityCourier akan otomatis muncul di sini untuk peninjauan KTP, SIM, dan STNK.
                @endif
            </p>
        </div>
        @endif
    </div>
</div>
@endsection
