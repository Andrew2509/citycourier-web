@extends('layouts.admin')

@section('title', 'Manajemen Armada Kurir')

@section('content')
@php
    $all = $couriers->getCollection();
    $verified = $all->where('is_verified', true)->values();
    $pending  = $all->where('is_verified', false)->values();

    $fleetTypes = $all->groupBy(fn ($c) => strtolower(trim($c->vehicle_type ?? 'lainnya')))->map->count();
    $fleetTypeLabels = [
        'motor' => 'Sepeda Motor',
        'mobil' => 'Mobil',
        'sepeda' => 'Sepeda',
    ];

    $courierData = [];
    foreach ($verified as $c) {
        $phoneRaw = preg_replace('/[^0-9]/', '', $c->phone ?? '');
        $wa = (str_starts_with($phoneRaw, '0')) ? '62' . substr($phoneRaw, 1)
            : ((str_starts_with($phoneRaw, '62')) ? $phoneRaw : '62' . $phoneRaw);
        $nik = $c->nik ? substr($c->nik, 0, 4) . '**********' . substr($c->nik, -4) : 'Belum dilengkapi';
        $vehicleLabel = $fleetTypeLabels[strtolower($c->vehicle_type ?? '')] ?? ucfirst($c->vehicle_type ?? 'Kendaraan');
        $vehicleIcon = match (strtolower($c->vehicle_type ?? '')) {
            'motor' => 'two_wheeler',
            'mobil' => 'directions_car',
            'sepeda' => 'pedal_bike',
            default => 'commute',
        };

        $courierData[$c->id] = [
            'name' => $c->user?->name ?? 'Kurir',
            'email' => $c->user?->email ?? '-',
            'phone' => $c->phone ?? '-',
            'wa' => $wa,
            'idLabel' => 'CC-KRR-' . ($c->created_at?->format('Y') ?? date('Y')) . '-' . str_pad($c->id, 3, '0', STR_PAD_LEFT),
            'nik' => $nik,
            'nikRaw' => $c->nik ?? '',
            'city' => $c->city ?? '',
            'vehicleType' => $c->vehicle_type ?? '',
            'vehicleBrand' => $c->vehicle_brand ?? '',
            'vehicleYear' => $c->vehicle_year ?? '',
            'vehicleLabel' => $vehicleLabel,
            'vehicleIcon' => $vehicleIcon,
            'plate' => $c->vehicle_plate ?? '-',
            'joined' => $c->created_at?->format('d M Y') ?? '-',
            'verified' => (bool) $c->is_verified,
            'active' => (bool) $c->is_active,
            'hasKtp' => (bool) $c->id_card_photo,
            'hasSim' => (bool) $c->driving_license_photo,
            'hasVehicle' => (bool) ($c->skck_photo || $c->photo),
            'recapCount' => $c->orders->count(),
            'recapTotal' => $c->orders->sum('price'),
        ];
    }
@endphp
<div class="flex flex-col w-full gap-space-xl">

    <!-- Flash Messages -->
    @if(session('success'))
        <div id="flashSuccess" class="p-space-md rounded-xl bg-emerald-50 border border-emerald-200 flex items-center gap-space-sm">
            <span class="material-symbols-outlined text-[20px] text-emerald-600">check_circle</span>
            <span class="font-body-sm text-body-sm text-emerald-800 font-semibold">{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div id="flashError" class="p-space-md rounded-xl bg-red-50 border border-red-200 flex items-center gap-space-sm">
            <span class="material-symbols-outlined text-[20px] text-red-600">error</span>
            <span class="font-body-sm text-body-sm text-red-800 font-semibold">{{ session('error') }}</span>
        </div>
    @endif

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
            <a href="{{ route('admin.couriers.export') }}" class="h-9 px-space-md rounded-lg bg-surface-container-lowest text-on-surface hover:bg-surface-container shadow-sm flex items-center gap-space-xs font-label-md text-label-md transition-colors">
                <span class="material-symbols-outlined text-[18px] text-secondary">file_download</span>
                <span>Export Data Kurir</span>
            </a>
            <button class="h-9 px-space-md rounded-lg bg-primary-container hover:bg-primary text-on-primary shadow-sm flex items-center gap-space-xs font-label-md text-label-md font-semibold transition-colors" onclick="openAddCourierModal()" type="button">
                <span class="material-symbols-outlined text-[18px]">person_add</span>
                <span>+ Tambah Kurir Baru</span>
            </button>
        </div>
    </div>

    <!-- Operational Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-space-md">
        <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Total Mitra Terdaftar</span>
                <span class="p-space-xs rounded-lg bg-surface-container-high text-primary flex items-center justify-center">
                    <span class="material-symbols-outlined text-[20px]">two_wheeler</span>
                </span>
            </div>
            <div class="flex items-baseline gap-space-xs mt-space-sm">
                <span class="font-display-lg text-display-lg text-on-surface font-bold">{{ $stats['total'] }}</span>
                <span class="font-label-sm text-label-sm text-secondary">Kurir Terdata</span>
            </div>
            <div class="flex items-center gap-space-xs mt-space-xs">
                <span class="material-symbols-outlined text-[14px] text-primary font-bold">arrow_upward</span>
                <span class="font-label-sm text-label-sm text-primary font-semibold">Aktif dalam sistem operasional</span>
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
                <span class="font-display-lg text-display-lg text-on-surface font-bold">{{ $stats['active'] }}</span>
                <span class="font-label-sm text-label-sm text-secondary">Aktif Bertugas</span>
            </div>
            <div class="flex items-center gap-space-xs mt-space-xs">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="font-label-sm text-label-sm text-secondary font-semibold">{{ $stats['total'] > 0 ? round($stats['active'] / $stats['total'] * 100) : 0 }}% Rasio Kesiapan Armada</span>
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
                <span class="font-display-lg text-display-lg text-on-surface font-bold">{{ $stats['unverified'] }}</span>
                <span class="font-label-sm text-label-sm text-secondary">Antrian Berkas</span>
            </div>
            <div class="flex items-center gap-space-xs mt-space-xs">
                <span class="material-symbols-outlined text-[14px] text-secondary font-bold">{{ $stats['unverified'] > 0 ? 'schedule' : 'check_circle' }}</span>
                <span class="font-label-sm text-label-sm text-secondary font-semibold">{{ $stats['unverified'] > 0 ? 'Ada pengajuan yang perlu ditinjau' : 'Semua pengajuan telah diproses' }}</span>
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
            <button class="tab-btn px-space-md py-space-xs rounded-lg font-label-md text-label-md font-medium text-secondary hover:text-on-surface hover:bg-surface-container-high transition-colors whitespace-nowrap" onclick="setActiveTab(this, 'all')" type="button">
                Semua
            </button>
            <button class="tab-btn px-space-md py-space-xs rounded-lg font-label-md text-label-md font-bold {{ request('filter') === 'unverified' ? '' : 'bg-primary-container text-on-primary shadow-sm' }} transition-colors whitespace-nowrap flex items-center gap-space-xs" onclick="setActiveTab(this, 'verified')" type="button">
                <span>Terverifikasi</span>
                <span class="px-1.5 py-0.2 rounded font-data-mono text-[11px]">{{ $stats['verified'] }}</span>
            </button>
            <button class="tab-btn px-space-md py-space-xs rounded-lg font-label-md text-label-md font-medium text-secondary hover:text-on-surface hover:bg-surface-container-high transition-colors whitespace-nowrap flex items-center gap-space-xs" onclick="setActiveTab(this, 'unverified')" type="button">
                <span>Belum Verifikasi</span>
                <span class="px-1.5 py-0.2 bg-surface-container-highest text-secondary rounded font-data-mono text-[11px]">{{ $stats['unverified'] }}</span>
            </button>
            <button class="tab-btn px-space-md py-space-xs rounded-lg font-label-md text-label-md font-medium text-secondary hover:text-on-surface hover:bg-surface-container-high transition-colors whitespace-nowrap flex items-center gap-space-xs" onclick="setActiveTab(this, 'active')" type="button">
                <span>Aktif Online</span>
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
            </button>
        </div>
        <!-- Live Search & Control -->
        <div class="flex items-center gap-space-sm flex-1 lg:max-w-md">
            <div class="relative w-full flex items-center">
                <span class="material-symbols-outlined absolute left-space-md text-secondary pointer-events-none text-[18px]">search</span>
                <input class="w-full bg-surface pl-9 pr-space-md py-space-xs h-9 rounded-lg font-body-sm text-body-sm text-on-surface placeholder:text-secondary focus:outline-none focus:bg-surface-container-lowest focus:ring-1 focus:ring-primary-container shadow-sm transition-all" id="searchInput" onkeyup="filterKurirTable()" placeholder="Cari nama, email, nomor telepon, atau plat kendaraan..." type="text"/>
            </div>
            <button class="p-space-xs h-9 w-9 rounded-lg bg-surface hover:bg-surface-container text-secondary hover:text-on-surface flex items-center justify-center transition-colors" title="Refresh data" onclick="location.reload()" type="button">
                <span class="material-symbols-outlined text-[18px]">refresh</span>
            </button>
        </div>
    </div>

    <!-- Primary Content Area: Table & Verification Panel -->
    <div class="flex flex-col gap-space-xl">

        <!-- SECTION 1: Verified Courier Fleet Table -->
        <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden flex flex-col" id="verifiedSection">
            <!-- Section Header -->
            <div class="px-space-lg py-space-md bg-surface-container-lowest flex items-center justify-between border-b border-surface-container-high">
                <div class="flex items-center gap-space-sm">
                    <div class="w-9 h-9 rounded-lg bg-primary-fixed flex items-center justify-center text-on-primary-fixed-variant">
                        <span class="material-symbols-outlined text-[20px]">two_wheeler</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="font-headline-sm text-headline-sm text-on-surface font-bold leading-tight">Daftar Kurir Terverifikasi</span>
                        <span class="font-label-sm text-label-sm text-secondary" id="activeCountLabel">Menampilkan {{ count($verified) }} kurir terverifikasi &amp; aktif</span>
                    </div>
                </div>
                <div class="flex items-center gap-space-xs">
                    <span class="px-space-xs py-space-2xs rounded-lg font-label-sm text-label-sm bg-surface-container text-secondary font-data-mono">Sync: Live Socket</span>
                </div>
            </div>

            <!-- High-Density Courier Table -->
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
                            <th class="py-space-sm px-space-md font-semibold" scope="col">Rekap Hari Ini</th>
                            <th class="py-space-sm px-space-lg font-semibold text-right" scope="col">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-container font-body-sm text-body-sm text-on-surface">
                    @forelse ($verified as $courier)
                        <tr class="hover:bg-surface-container-low transition-colors group">
                            <!-- Courier Profile -->
                            <td class="py-space-md px-space-lg">
                                <div class="flex items-center gap-space-sm">
                                    <div class="w-10 h-10 rounded-full bg-primary-container text-on-primary flex items-center justify-center font-headline-sm text-headline-sm font-bold shadow-sm shrink-0">
                                        {{ strtoupper(substr($courierData[$courier->id]['name'], 0, 1)) }}
                                    </div>
                                    <div class="flex flex-col min-w-0">
                                        <span class="font-headline-sm text-headline-sm text-on-surface font-semibold truncate">{{ $courierData[$courier->id]['name'] }}</span>
                                        <span class="font-body-sm text-body-sm text-secondary truncate font-data-mono">{{ $courierData[$courier->id]['email'] }}</span>
                                        <span class="font-label-sm text-label-sm text-secondary">Bergabung: {{ $courierData[$courier->id]['joined'] }}</span>
                                    </div>
                                </div>
                            </td>
                            <!-- Phone / Quick WhatsApp -->
                            <td class="py-space-md px-space-md">
                                <div class="flex items-center gap-space-xs">
                                    <span class="font-data-mono text-data-mono text-on-surface font-medium">{{ $courierData[$courier->id]['phone'] }}</span>
                                    <a class="p-1 rounded text-emerald-600 hover:bg-emerald-50 transition-colors flex items-center justify-center" href="https://wa.me/{{ $courierData[$courier->id]['wa'] }}" target="_blank" title="Kirim Pesan WhatsApp">
                                        <span class="material-symbols-outlined text-[16px]">chat</span>
                                    </a>
                                </div>
                            </td>
                            <!-- Vehicle Badge -->
                            <td class="py-space-md px-space-md">
                                <div class="inline-flex items-center gap-space-2xs px-space-sm py-0.5 rounded-lg bg-secondary-fixed text-on-secondary-fixed font-label-md text-label-md font-medium">
                                    <span class="material-symbols-outlined text-[16px]">{{ $courierData[$courier->id]['vehicleIcon'] }}</span>
                                    <span>{{ $courierData[$courier->id]['vehicleLabel'] }}</span>
                                </div>
                            </td>
                            <!-- Vehicle License Plate (Indonesian Style) -->
                            <td class="py-space-md px-space-md">
                                <div class="inline-flex items-center px-space-sm py-0.5 rounded bg-surface-container-high text-on-surface font-data-mono text-data-mono font-bold tracking-wider uppercase">
                                    {{ $courierData[$courier->id]['plate'] }}
                                </div>
                            </td>
                            <!-- Verification Badge -->
                            <td class="py-space-md px-space-md">
                                @if ($courierData[$courier->id]['verified'])
                                <span class="inline-flex items-center gap-space-2xs px-space-sm py-0.5 rounded-lg bg-emerald-50 text-emerald-700 font-label-md text-label-md font-semibold">
                                    <span class="material-symbols-outlined text-[15px] font-bold">check_circle</span>
                                    <span>Terverifikasi</span>
                                </span>
                                @else
                                <span class="inline-flex items-center gap-space-2xs px-space-sm py-0.5 rounded-lg bg-surface-container-highest text-secondary font-label-md text-label-md font-semibold">
                                    <span class="material-symbols-outlined text-[15px] font-bold">hourglass_top</span>
                                    <span>Menunggu</span>
                                </span>
                                @endif
                            </td>
                            <!-- Online/Duty Status -->
                            <td class="py-space-md px-space-md">
                                @if ($courierData[$courier->id]['active'])
                                <div class="inline-flex items-center gap-space-2xs px-space-sm py-0.5 rounded-lg bg-emerald-50 text-emerald-800 font-label-md text-label-md font-semibold">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                                    <span>Aktif</span>
                                </div>
                                @else
                                <div class="inline-flex items-center gap-space-2xs px-space-sm py-0.5 rounded-lg bg-surface-container text-secondary font-label-md text-label-md font-medium">
                                    <span class="w-2 h-2 rounded-full bg-outline"></span>
                                    <span>Nonaktif</span>
                                </div>
                                @endif
                            </td>
                            <!-- Daily Summary Metric -->
                            <td class="py-space-md px-space-md">
                                <div class="flex flex-col">
                                    <div class="flex items-center gap-space-2xs">
                                        <span class="material-symbols-outlined text-[15px] text-primary">task_alt</span>
                                        <span class="font-label-md text-label-md font-semibold text-on-surface">{{ $courierData[$courier->id]['recapCount'] }} Tugas Selesai</span>
                                    </div>
                                    <span class="font-label-sm text-label-sm text-secondary font-data-mono">Omzet Rp {{ number_format($courierData[$courier->id]['recapTotal'], 0, ',', '.') }}</span>
                                </div>
                            </td>
                            <!-- Quick Actions -->
                            <td class="py-space-md px-space-lg text-right">
                                <div class="inline-flex items-center justify-end gap-space-2xs">
                                    <button class="p-space-xs rounded-lg text-secondary hover:text-on-surface hover:bg-surface-container transition-colors" onclick="openDriverModal({{ $courier->id }})" title="Lihat Profil &amp; Dokumen" type="button">
                                        <span class="material-symbols-outlined text-[18px]">visibility</span>
                                    </button>
                                    <button class="p-space-xs rounded-lg text-secondary hover:text-on-surface hover:bg-surface-container transition-colors" onclick="openEditModal({{ $courier->id }})" title="Edit Data Armada" type="button">
                                        <span class="material-symbols-outlined text-[18px]">edit</span>
                                    </button>
                                    <form action="{{ route('admin.couriers.toggle-active', $courier->id) }}" method="POST" class="inline-block" onsubmit="return confirmSuspend('{{ addslashes($courierData[$courier->id]['name']) }}')">
                                        @csrf
                                        @method('PATCH')
                                        <button class="p-space-xs rounded-lg text-secondary hover:text-error hover:bg-error-container/20 transition-colors" title="{{ $courierData[$courier->id]['active'] ? 'Nonaktifkan / Suspend Akun' : 'Aktifkan Akun' }}" type="submit">
                                            <span class="material-symbols-outlined text-[18px]">{{ $courierData[$courier->id]['active'] ? 'block' : 'check_circle' }}</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-space-2xl text-center">
                                <div class="flex flex-col items-center justify-center text-center">
                                    <span class="material-symbols-outlined text-[36px] text-secondary">two_wheeler</span>
                                    <span class="font-headline-sm text-headline-sm text-on-surface font-bold mt-space-sm">Belum ada kurir terverifikasi</span>
                                    <span class="font-body-sm text-body-sm text-secondary">Kurir yang telah diverifikasi akan tampil di sini.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Table Footer / Pagination -->
            <div class="p-space-md bg-surface flex flex-col sm:flex-row items-center justify-between gap-space-sm">
                <span class="font-label-sm text-label-sm text-secondary">
                    Menampilkan {{ $couriers->firstItem() ?? 0 }}-{{ $couriers->lastItem() ?? 0 }} dari {{ $couriers->total() }} total kurir
                </span>
                <div class="flex items-center gap-space-xs">
                    @if ($couriers->previousPageUrl())
                        <a href="{{ $couriers->previousPageUrl() }}" class="px-space-sm py-1 rounded bg-surface-container-high text-secondary font-label-sm text-label-sm">Sebelumnya</a>
                    @else
                        <button class="px-space-sm py-1 rounded bg-surface-container-high text-secondary opacity-50 cursor-not-allowed font-label-sm text-label-sm" disabled type="button">Sebelumnya</button>
                    @endif
                    @for ($i = 1; $i <= $couriers->lastPage(); $i++)
                        @if ($i === $couriers->currentPage())
                            <span class="px-space-sm py-1 rounded bg-primary-container text-on-primary font-data-mono text-data-mono font-bold">{{ $i }}</span>
                        @else
                            <a href="{{ $couriers->url($i) }}" class="px-space-sm py-1 rounded bg-surface-container-high text-secondary font-data-mono text-data-mono">{{ $i }}</a>
                        @endif
                    @endfor
                    @if ($couriers->nextPageUrl())
                        <a href="{{ $couriers->nextPageUrl() }}" class="px-space-sm py-1 rounded bg-surface-container-high text-secondary font-label-sm text-label-sm">Selanjutnya</a>
                    @else
                        <button class="px-space-sm py-1 rounded bg-surface-container-high text-secondary opacity-50 cursor-not-allowed font-label-sm text-label-sm" disabled type="button">Selanjutnya</button>
                    @endif
                </div>
            </div>
        </div>

        <!-- SECTION 2: Courier Pending Verification -->
        <div class="bg-surface-container-lowest rounded-xl shadow-sm p-space-xl flex flex-col {{ count($pending) === 0 ? '' : 'gap-space-md' }}" id="pendingSection">
            <div class="flex items-center justify-between pb-space-md border-b border-surface-container-high mb-space-lg">
                <div class="flex items-center gap-space-sm">
                    <div class="w-9 h-9 rounded-lg bg-surface-container flex items-center justify-center text-secondary">
                        <span class="material-symbols-outlined text-[20px]">how_to_reg</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="font-headline-sm text-headline-sm text-on-surface font-bold leading-tight">Kurir Menunggu Verifikasi</span>
                        <span class="font-label-sm text-label-sm text-secondary">Total: {{ count($pending) }} kurir dalam antrean persetujuan berkas</span>
                    </div>
                </div>
                <button class="flex items-center gap-space-2xs text-secondary hover:text-primary font-label-md text-label-md transition-colors" onclick="location.reload()" type="button">
                    <span class="material-symbols-outlined text-[16px]">sync</span>
                    <span>Periksa Pendaftaran Baru</span>
                </button>
            </div>

            @forelse ($pending as $courier)
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-space-md p-space-md rounded-xl bg-surface-container-low border border-surface-container-high">
                    <div class="flex items-center gap-space-md min-w-0">
                        <div class="w-10 h-10 rounded-full bg-primary-fixed text-on-primary-fixed flex items-center justify-center font-headline-sm text-headline-sm font-bold shrink-0">
                            {{ strtoupper(substr($courier->user?->name ?? 'K', 0, 1)) }}
                        </div>
                        <div class="flex flex-col min-w-0">
                            <span class="font-headline-sm text-headline-sm text-on-surface font-semibold truncate">{{ $courier->user?->name ?? 'Kurir' }}</span>
                            <span class="font-body-sm text-body-sm text-secondary truncate font-data-mono">{{ $courier->user?->email ?? '-' }}</span>
                            <span class="font-label-sm text-label-sm text-secondary">Mendaftar: {{ $courier->created_at?->format('d M Y') ?? '-' }} &bull; {{ ucfirst($courier->vehicle_type ?? 'Kendaraan') }} &bull; {{ $courier->vehicle_plate ?? '-' }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-space-sm shrink-0">
                        <form action="{{ route('admin.couriers.verify', $courier->id) }}" method="POST" class="inline-block" onsubmit="return confirmSteer()">
                            @csrf
                            @method('PATCH')
                            <button class="h-9 px-space-md rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-label-md text-label-md shadow-sm flex items-center gap-space-xs" type="submit">
                                <span class="material-symbols-outlined text-[16px]">verified</span>
                                <span>Verifikasi Sekarang</span>
                            </button>
                        </form>
                        <form action="{{ route('admin.couriers.toggle-active', $courier->id) }}" method="POST" class="inline-block" onsubmit="return confirmSuspend('{{ addslashes($courier->user?->name ?? 'Kurir') }}')">
                            @csrf
                            @method('PATCH')
                            <button class="h-9 px-space-md rounded-lg bg-surface-container-high text-secondary font-label-md text-label-md flex items-center gap-space-xs hover:text-error transition-colors" type="submit">
                                <span class="material-symbols-outlined text-[16px]">block</span>
                                <span>Tolak</span>
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <!-- Empty State Card -->
                <div class="py-space-2xl px-space-lg flex flex-col items-center justify-center text-center max-w-lg mx-auto">
                    <div class="w-16 h-16 rounded-full bg-surface-container-high flex items-center justify-center text-secondary mb-space-md shadow-inner">
                        <span class="material-symbols-outlined text-[32px] text-secondary">group_off</span>
                    </div>
                    <h3 class="font-headline-lg text-headline-lg text-on-surface font-bold">Belum ada kurir</h3>
                    <p class="font-body-md text-body-md text-secondary mt-space-xs leading-relaxed">
                        Kurir yang mendaftar dari aplikasi CityCourier akan otomatis muncul di sini untuk peninjauan KTP, SIM, dan STNK.
                    </p>
                    <div class="flex items-center gap-space-sm mt-space-lg">
                        <button class="h-9 px-space-md rounded-lg bg-surface-container-high hover:bg-surface-container-highest text-on-surface font-label-md text-label-md transition-colors flex items-center gap-space-xs" onclick="shareRegistrationLink()" type="button">
                            <span class="material-symbols-outlined text-[16px]">share</span>
                            <span>Bagikan Link Pendaftaran Kurir</span>
                        </button>
                    </div>
                </div>
            @endforelse
        </div>

        <!-- Quick Operations Insights & Live Verification Checklist Info -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-space-md">
            <!-- Standard Operational Policy -->
            <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between">
                <div class="flex flex-col gap-space-2xs">
                    <div class="flex items-center gap-space-xs text-primary">
                        <span class="material-symbols-outlined text-[20px]">verified_user</span>
                        <span class="font-headline-sm text-headline-sm font-bold text-on-surface">Protokol Verifikasi</span>
                    </div>
                    <p class="font-body-sm text-body-sm text-secondary mt-space-xs">
                        Setiap mitra wajib mengunggah KTP aktif, SIM C / SIM A yang masih berlaku, serta STNK kendaraan yang cocok dengan nomor plat yang tertera di sistem.
                    </p>
                </div>
                <div class="pt-space-md border-t border-surface-container-high flex items-center justify-between text-secondary font-label-sm text-label-sm">
                    <span>SLA Validasi: &lt; 15 Menit</span>
                    <span class="text-primary font-semibold">Lihat SOP &rarr;</span>
                </div>
            </div>
            <!-- Vehicle Dispatch Split -->
            <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between">
                <div class="flex flex-col gap-space-2xs">
                    <div class="flex items-center gap-space-xs text-secondary">
                        <span class="material-symbols-outlined text-[20px]">commute</span>
                        <span class="font-headline-sm text-headline-sm font-bold text-on-surface">Komposisi Armada</span>
                    </div>
                    @if ($fleetTypes->isNotEmpty())
                        @foreach ($fleetTypes->take(3) as $type => $count)
                            <div class="flex items-center justify-between mt-space-md">
                                <div class="flex items-center gap-space-sm">
                                    <span class="w-3 h-3 rounded-full bg-primary-container"></span>
                                    <span class="font-body-sm text-body-sm text-on-surface">{{ $fleetTypeLabels[$type] ?? ucfirst($type) }}</span>
                                </div>
                                <span class="font-data-mono text-data-mono font-bold text-on-surface">{{ $count }} Unit ({{ round($count / $fleetTypes->sum() * 100) }}%)</span>
                            </div>
                            <div class="w-full bg-surface-container h-2 rounded-full overflow-hidden mt-space-xs">
                                <div class="bg-primary-container h-full" style="width: {{ round($count / $fleetTypes->sum() * 100) }}%"></div>
                            </div>
                        @endforeach
                    @else
                        <div class="flex items-center justify-between mt-space-md">
                            <span class="font-body-sm text-body-sm text-secondary">Belum ada data kendaraan</span>
                        </div>
                        <div class="w-full bg-surface-container h-2 rounded-full overflow-hidden mt-space-xs"></div>
                    @endif
                </div>
                <div class="pt-space-md border-t border-surface-container-high flex items-center justify-between text-secondary font-label-sm text-label-sm">
                    <span>Kapasitas Muat Reguler</span>
                    <span class="font-data-mono">Max 25kg / rider</span>
                </div>
            </div>
            <!-- Instant Support Quick Card -->
            <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between">
                <div class="flex flex-col gap-space-2xs">
                    <div class="flex items-center gap-space-xs text-primary">
                        <span class="material-symbols-outlined text-[20px]">support_agent</span>
                        <span class="font-headline-sm text-headline-sm font-bold text-on-surface">Bantuan Operasional</span>
                    </div>
                    <p class="font-body-sm text-body-sm text-secondary mt-space-xs">
                        Pusat kontak langsung dispatcher ke tim pengawas lapangan untuk penanganan kendala penjemputan barang atau insiden teknis kendaraan.
                    </p>
                </div>
                <div class="pt-space-md border-t border-surface-container-high flex items-center justify-between">
                    <span class="font-label-sm text-label-sm text-emerald-600 font-semibold flex items-center gap-1">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Hotline Siaga
                    </span>
                    <a class="h-8 px-space-sm rounded-lg bg-surface-container text-on-surface hover:bg-surface-container-high font-label-sm text-label-sm flex items-center gap-1 transition-colors" href="tel:081343323155">
                        <span class="material-symbols-outlined text-[15px]">call</span>
                        <span>Hubungi Lapangan</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Driver Modal -->
    <div class="fixed inset-0 z-50 bg-inverse-surface/40 backdrop-blur-sm hidden items-center justify-center p-space-md" id="driverDetailModal" onclick="if(event.target === this) closeDriverModal()">
        <div class="bg-surface-container-lowest rounded-xl shadow-xl w-full max-w-xl overflow-hidden flex flex-col animate-in fade-in zoom-in-95 duration-150">
            <!-- Modal Header -->
            <div class="px-space-xl py-space-md bg-surface-container flex items-center justify-between">
                <div class="flex items-center gap-space-sm">
                    <span class="material-symbols-outlined text-[22px] text-primary">badge</span>
                    <span class="font-headline-sm text-headline-sm text-on-surface font-bold">Detail Dokumen Mitra Kurir</span>
                </div>
                <button class="text-secondary hover:text-on-surface p-1 rounded transition-colors" onclick="closeDriverModal()" type="button">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>
            <!-- Modal Body -->
            <div class="p-space-xl flex flex-col gap-space-lg max-h-[80vh] overflow-y-auto">
                <div class="flex items-center gap-space-md">
                    <div class="w-14 h-14 rounded-full bg-primary-container text-on-primary flex items-center justify-center font-display-lg text-display-lg font-bold" id="modalDriverAvatar">
                        P
                    </div>
                    <div class="flex flex-col">
                        <div class="flex items-center gap-space-xs">
                            <h4 class="font-headline-lg text-headline-lg font-bold text-on-surface" id="modalDriverName">Princeton</h4>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 font-label-sm text-label-sm font-semibold" id="modalDriverBadge">
                                <span class="material-symbols-outlined text-[13px]">verified</span> Terverifikasi
                            </span>
                        </div>
                        <span class="font-body-sm text-body-sm text-secondary" id="modalDriverEmailPhone">masbrightly@gmail.com &bull; 081343323155</span>
                        <span class="font-label-sm text-label-sm text-secondary font-data-mono" id="modalDriverId">ID Kurir: CC-KRR-2024-001</span>
                    </div>
                </div>
                <!-- Vehicle Detail Box -->
                <div class="bg-surface p-space-md rounded-lg flex flex-col gap-space-xs">
                    <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Spesifikasi Kendaraan</span>
                    <div class="grid grid-cols-3 gap-space-sm mt-space-2xs text-left">
                        <div>
                            <span class="font-label-sm text-label-sm text-secondary block">Jenis</span>
                            <span class="font-headline-sm text-headline-sm text-on-surface font-semibold" id="modalVehicleType">Sepeda Motor</span>
                        </div>
                        <div>
                            <span class="font-label-sm text-label-sm text-secondary block">Nomor Polisi</span>
                            <span class="font-data-mono text-data-mono text-on-surface font-bold" id="modalVehiclePlate">B 1234 ABC</span>
                        </div>
                        <div>
                            <span class="font-label-sm text-label-sm text-secondary block">Masa Berlaku STNK</span>
                            <span class="font-data-mono text-data-mono text-emerald-700 font-medium" id="modalVehicleYear">Terdaftar di sistem</span>
                        </div>
                    </div>
                </div>
                <!-- Document Status Checklist -->
                <div class="flex flex-col gap-space-sm">
                    <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Kelengkapan Dokumen Fisik</span>
                    <div class="flex items-center justify-between p-space-sm rounded-lg bg-surface-container-low">
                        <div class="flex items-center gap-space-sm">
                            <span class="material-symbols-outlined text-[20px] text-emerald-600">badge</span>
                            <div class="flex flex-col">
                                <span class="font-body-sm text-body-sm font-semibold text-on-surface">KTP Elektronik</span>
                                <span class="font-label-sm text-label-sm text-secondary font-data-mono" id="modalDocKtp">NIK: Belum dilengkapi</span>
                            </div>
                        </div>
                        <span class="px-space-xs py-0.5 rounded font-label-sm text-label-sm font-semibold" id="modalDocKtpStatus">Belum</span>
                    </div>
                    <div class="flex items-center justify-between p-space-sm rounded-lg bg-surface-container-low">
                        <div class="flex items-center gap-space-sm">
                            <span class="material-symbols-outlined text-[20px] text-emerald-600">credit_card</span>
                            <div class="flex flex-col">
                                <span class="font-body-sm text-body-sm font-semibold text-on-surface">SIM C Aktif</span>
                                <span class="font-label-sm text-label-sm text-secondary font-data-mono" id="modalDocSim">Berlaku s/d: Mengecek berkas</span>
                            </div>
                        </div>
                        <span class="px-space-xs py-0.5 rounded font-label-sm text-label-sm font-semibold" id="modalDocSimStatus">Belum</span>
                    </div>
                    <div class="flex items-center justify-between p-space-sm rounded-lg bg-surface-container-low">
                        <div class="flex items-center gap-space-sm">
                            <span class="material-symbols-outlined text-[20px] text-emerald-600">receipt_long</span>
                            <div class="flex flex-col">
                                <span class="font-body-sm text-body-sm font-semibold text-on-surface">Foto Kendaraan &amp; Plat Nomor</span>
                                <span class="font-label-sm text-label-sm text-secondary" id="modalDocVehicle">Tampak depan &amp; nomor rangka sesuai</span>
                            </div>
                        </div>
                        <span class="px-space-xs py-0.5 rounded font-label-sm text-label-sm font-semibold" id="modalDocVehicleStatus">Belum</span>
                    </div>
                </div>
            </div>
            <!-- Modal Actions -->
            <div class="p-space-lg bg-surface flex items-center justify-between">
                <button class="px-space-md py-space-xs rounded-lg bg-surface-container text-secondary hover:text-on-surface font-label-md text-label-md" onclick="closeDriverModal()" type="button">
                    Tutup
                </button>
                <div class="flex items-center gap-space-xs">
                    <a class="h-9 px-space-md rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-label-md text-label-md flex items-center gap-space-xs shadow-sm" id="modalWaLink" href="#" target="_blank">
                        <span class="material-symbols-outlined text-[16px]">chat</span>
                        <span>Hubungi Kurir</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Courier Modal -->
    <div class="fixed inset-0 z-50 bg-inverse-surface/40 backdrop-blur-sm hidden items-center justify-center p-space-md" id="addCourierModal" onclick="if(event.target === this) closeAddCourierModal()">
        <div class="bg-surface-container-lowest rounded-xl shadow-xl w-full max-w-2xl overflow-hidden flex flex-col animate-in fade-in zoom-in-95 duration-150">
            <!-- Modal Header -->
            <div class="px-space-xl py-space-md bg-surface-container flex items-center justify-between">
                <div class="flex items-center gap-space-sm">
                    <span class="material-symbols-outlined text-[22px] text-primary">person_add</span>
                    <span class="font-headline-sm text-headline-sm text-on-surface font-bold">Tambah Kurir Baru</span>
                </div>
                <button class="text-secondary hover:text-on-surface p-1 rounded transition-colors" onclick="closeAddCourierModal()" type="button">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>
            <!-- Modal Body -->
            <form action="{{ route('admin.couriers.store') }}" method="POST">
                @csrf
                <div class="p-space-xl flex flex-col gap-space-lg max-h-[80vh] overflow-y-auto">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-md">
                        <div class="flex flex-col gap-space-2xs">
                            <label class="font-label-sm text-label-sm text-secondary font-semibold" for="addName">Nama Lengkap <span class="text-error">*</span></label>
                            <input class="w-full bg-surface px-space-md py-space-sm h-9 rounded-lg font-body-sm text-body-sm text-on-surface placeholder:text-secondary focus:outline-none focus:ring-1 focus:ring-primary-container border border-outline-variant" id="addName" name="name" required placeholder="Nama mitra kurir" type="text"/>
                        </div>
                        <div class="flex flex-col gap-space-2xs">
                            <label class="font-label-sm text-label-sm text-secondary font-semibold" for="addEmail">Email <span class="text-error">*</span></label>
                            <input class="w-full bg-surface px-space-md py-space-sm h-9 rounded-lg font-body-sm text-body-sm text-on-surface placeholder:text-secondary focus:outline-none focus:ring-1 focus:ring-primary-container border border-outline-variant" id="addEmail" name="email" required placeholder="nama@email.com" type="email"/>
                        </div>
                        <div class="flex flex-col gap-space-2xs">
                            <label class="font-label-sm text-label-sm text-secondary font-semibold" for="addPassword">Password Akun</label>
                            <input class="w-full bg-surface px-space-md py-space-sm h-9 rounded-lg font-body-sm text-body-sm text-on-surface placeholder:text-secondary focus:outline-none focus:ring-1 focus:ring-primary-container border border-outline-variant" id="addPassword" name="password" placeholder="Kosongkan = diacak otomatis" type="text"/>
                        </div>
                        <div class="flex flex-col gap-space-2xs">
                            <label class="font-label-sm text-label-sm text-secondary font-semibold" for="addPhone">Telepon / WhatsApp</label>
                            <input class="w-full bg-surface px-space-md py-space-sm h-9 rounded-lg font-body-sm text-body-sm text-on-surface placeholder:text-secondary focus:outline-none focus:ring-1 focus:ring-primary-container border border-outline-variant" id="addPhone" name="phone" placeholder="08xxxxxxxxxx" type="text"/>
                        </div>
                        <div class="flex flex-col gap-space-2xs">
                            <label class="font-label-sm text-label-sm text-secondary font-semibold" for="addNik">NIK</label>
                            <input class="w-full bg-surface px-space-md py-space-sm h-9 rounded-lg font-body-sm text-body-sm text-on-surface placeholder:text-secondary focus:outline-none focus:ring-1 focus:ring-primary-container border border-outline-variant" id="addNik" name="nik" maxlength="16" placeholder="16 digit NIK" type="text"/>
                        </div>
                        <div class="flex flex-col gap-space-2xs">
                            <label class="font-label-sm text-label-sm text-secondary font-semibold" for="addCity">Kota</label>
                            <input class="w-full bg-surface px-space-md py-space-sm h-9 rounded-lg font-body-sm text-body-sm text-on-surface placeholder:text-secondary focus:outline-none focus:ring-1 focus:ring-primary-container border border-outline-variant" id="addCity" name="city" placeholder="Kota domisili" type="text"/>
                        </div>
                        <div class="flex flex-col gap-space-2xs">
                            <label class="font-label-sm text-label-sm text-secondary font-semibold" for="addVehicleType">Jenis Kendaraan <span class="text-error">*</span></label>
                            <select class="w-full bg-surface px-space-md py-space-sm h-9 rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-1 focus:ring-primary-container border border-outline-variant" id="addVehicleType" name="vehicle_type" required>
                                <option value="motor">Sepeda Motor</option>
                                <option value="mobil">Mobil</option>
                                <option value="sepeda">Sepeda</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-space-2xs">
                            <label class="font-label-sm text-label-sm text-secondary font-semibold" for="addVehicleBrand">Merk / Model Kendaraan</label>
                            <input class="w-full bg-surface px-space-md py-space-sm h-9 rounded-lg font-body-sm text-body-sm text-on-surface placeholder:text-secondary focus:outline-none focus:ring-1 focus:ring-primary-container border border-outline-variant" id="addVehicleBrand" name="vehicle_brand" placeholder="cth: Honda Vario 160" type="text"/>
                        </div>
                        <div class="flex flex-col gap-space-2xs">
                            <label class="font-label-sm text-label-sm text-secondary font-semibold" for="addVehicleYear">Tahun Kendaraan</label>
                            <input class="w-full bg-surface px-space-md py-space-sm h-9 rounded-lg font-body-sm text-body-sm text-on-surface placeholder:text-secondary focus:outline-none focus:ring-1 focus:ring-primary-container border border-outline-variant" id="addVehicleYear" name="vehicle_year" maxlength="4" placeholder="cth: 2023" type="text"/>
                        </div>
                        <div class="flex flex-col gap-space-2xs">
                            <label class="font-label-sm text-label-sm text-secondary font-semibold" for="addPlate">Plat Nomor <span class="text-error">*</span></label>
                            <input class="w-full bg-surface px-space-md py-space-sm h-9 rounded-lg font-body-sm text-body-sm text-on-surface placeholder:text-secondary focus:outline-none focus:ring-1 focus:ring-primary-container border border-outline-variant uppercase" id="addPlate" name="vehicle_plate" required placeholder="B 1234 ABC" type="text"/>
                        </div>
                    </div>
                    <label class="flex items-center gap-space-sm cursor-pointer select-none">
                        <input class="w-4 h-4 rounded accent-[#9d4300]" id="addActivate" name="activate" value="1" checked type="checkbox"/>
                        <span class="font-body-sm text-body-sm text-on-surface">Aktif &amp; terverifikasi langsung (bisa langsung menerima pesanan)</span>
                    </label>
                </div>
                <!-- Modal Actions -->
                <div class="p-space-lg bg-surface flex items-center justify-between border-t border-surface-container-high">
                    <button class="px-space-md py-space-xs rounded-lg bg-surface-container text-secondary hover:text-on-surface font-label-md text-label-md" onclick="closeAddCourierModal()" type="button">
                        Batal
                    </button>
                    <button class="h-9 px-space-md rounded-lg bg-primary-container hover:bg-primary text-on-primary font-label-md text-label-md font-semibold shadow-sm flex items-center gap-space-xs" type="submit">
                        <span class="material-symbols-outlined text-[16px]">person_add</span>
                        <span>Simpan Kurir</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Courier Modal -->
    <div class="fixed inset-0 z-50 bg-inverse-surface/40 backdrop-blur-sm hidden items-center justify-center p-space-md" id="editCourierModal" onclick="if(event.target === this) closeEditModal()">
        <div class="bg-surface-container-lowest rounded-xl shadow-xl w-full max-w-2xl overflow-hidden flex flex-col animate-in fade-in zoom-in-95 duration-150">
            <!-- Modal Header -->
            <div class="px-space-xl py-space-md bg-surface-container flex items-center justify-between">
                <div class="flex items-center gap-space-sm">
                    <span class="material-symbols-outlined text-[22px] text-primary">edit</span>
                    <span class="font-headline-sm text-headline-sm text-on-surface font-bold">Edit Data Armada Kurir</span>
                </div>
                <button class="text-secondary hover:text-on-surface p-1 rounded transition-colors" onclick="closeEditModal()" type="button">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>
            <!-- Modal Body -->
            <form id="editCourierForm" action="#" method="POST">
                @csrf
                @method('PUT')
                <div class="p-space-xl flex flex-col gap-space-lg max-h-[80vh] overflow-y-auto">
                    <div class="flex items-center gap-space-sm">
                        <div class="w-10 h-10 rounded-full bg-primary-container text-on-primary flex items-center justify-center font-headline-sm text-headline-sm font-bold shrink-0" id="editAvatar">P</div>
                        <div class="flex flex-col">
                            <span class="font-headline-sm text-headline-sm text-on-surface font-semibold" id="editIdentityLabel">Kurir</span>
                            <span class="font-label-sm text-label-sm text-secondary font-data-mono" id="editIdLabel">ID Kurir: -</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-md">
                        <div class="flex flex-col gap-space-2xs sm:col-span-2">
                            <label class="font-label-sm text-label-sm text-secondary font-semibold" for="editName">Nama Lengkap <span class="text-error">*</span></label>
                            <input class="w-full bg-surface px-space-md py-space-sm h-9 rounded-lg font-body-sm text-body-sm text-on-surface placeholder:text-secondary focus:outline-none focus:ring-1 focus:ring-primary-container border border-outline-variant" id="editName" name="name" required type="text"/>
                        </div>
                        <div class="flex flex-col gap-space-2xs">
                            <label class="font-label-sm text-label-sm text-secondary font-semibold" for="editPhone">Telepon / WhatsApp</label>
                            <input class="w-full bg-surface px-space-md py-space-sm h-9 rounded-lg font-body-sm text-body-sm text-on-surface placeholder:text-secondary focus:outline-none focus:ring-1 focus:ring-primary-container border border-outline-variant" id="editPhone" name="phone" type="text"/>
                        </div>
                        <div class="flex flex-col gap-space-2xs">
                            <label class="font-label-sm text-label-sm text-secondary font-semibold" for="editNik">NIK</label>
                            <input class="w-full bg-surface px-space-md py-space-sm h-9 rounded-lg font-body-sm text-body-sm text-on-surface placeholder:text-secondary focus:outline-none focus:ring-1 focus:ring-primary-container border border-outline-variant" id="editNik" name="nik" maxlength="16" type="text"/>
                        </div>
                        <div class="flex flex-col gap-space-2xs">
                            <label class="font-label-sm text-label-sm text-secondary font-semibold" for="editCity">Kota</label>
                            <input class="w-full bg-surface px-space-md py-space-sm h-9 rounded-lg font-body-sm text-body-sm text-on-surface placeholder:text-secondary focus:outline-none focus:ring-1 focus:ring-primary-container border border-outline-variant" id="editCity" name="city" type="text"/>
                        </div>
                        <div class="flex flex-col gap-space-2xs">
                            <label class="font-label-sm text-label-sm text-secondary font-semibold" for="editVehicleType">Jenis Kendaraan <span class="text-error">*</span></label>
                            <select class="w-full bg-surface px-space-md py-space-sm h-9 rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-1 focus:ring-primary-container border border-outline-variant" id="editVehicleType" name="vehicle_type" required>
                                <option value="motor">Sepeda Motor</option>
                                <option value="mobil">Mobil</option>
                                <option value="sepeda">Sepeda</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-space-2xs">
                            <label class="font-label-sm text-label-sm text-secondary font-semibold" for="editVehicleBrand">Merk / Model Kendaraan</label>
                            <input class="w-full bg-surface px-space-md py-space-sm h-9 rounded-lg font-body-sm text-body-sm text-on-surface placeholder:text-secondary focus:outline-none focus:ring-1 focus:ring-primary-container border border-outline-variant" id="editVehicleBrand" name="vehicle_brand" type="text"/>
                        </div>
                        <div class="flex flex-col gap-space-2xs">
                            <label class="font-label-sm text-label-sm text-secondary font-semibold" for="editVehicleYear">Tahun Kendaraan</label>
                            <input class="w-full bg-surface px-space-md py-space-sm h-9 rounded-lg font-body-sm text-body-sm text-on-surface placeholder:text-secondary focus:outline-none focus:ring-1 focus:ring-primary-container border border-outline-variant" id="editVehicleYear" name="vehicle_year" maxlength="4" type="text"/>
                        </div>
                        <div class="flex flex-col gap-space-2xs">
                            <label class="font-label-sm text-label-sm text-secondary font-semibold" for="editPlate">Plat Nomor <span class="text-error">*</span></label>
                            <input class="w-full bg-surface px-space-md py-space-sm h-9 rounded-lg font-body-sm text-body-sm text-on-surface placeholder:text-secondary focus:outline-none focus:ring-1 focus:ring-primary-container border border-outline-variant uppercase" id="editPlate" name="vehicle_plate" required type="text"/>
                        </div>
                    </div>
                    <label class="flex items-center gap-space-sm cursor-pointer select-none">
                        <input class="w-4 h-4 rounded accent-[#9d4300]" id="editIsActive" name="is_active" value="1" type="checkbox"/>
                        <span class="font-body-sm text-body-sm text-on-surface">Akun Kurir Aktif</span>
                    </label>
                </div>
                <!-- Modal Actions -->
                <div class="p-space-lg bg-surface flex items-center justify-between border-t border-surface-container-high">
                    <button class="px-space-md py-space-xs rounded-lg bg-surface-container text-secondary hover:text-on-surface font-label-md text-label-md" onclick="closeEditModal()" type="button">
                        Batal
                    </button>
                    <button class="h-9 px-space-md rounded-lg bg-primary-container hover:bg-primary text-on-primary font-label-md text-label-md font-semibold shadow-sm flex items-center gap-space-xs" type="submit">
                        <span class="material-symbols-outlined text-[16px]">save</span>
                        <span>Simpan Perubahan</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    window.__couriers = @json($courierData);

    let __initialTab = @json(request('filter'));
    if (__initialTab === 'unverified') {
        document.addEventListener('DOMContentLoaded', function () {
            const tab = document.querySelector('#kurirFilterTabs .tab-btn[onclick*="unverified"]');
            if (tab) setActiveTab(tab, 'unverified');
        });
    }

    function setActiveTab(button, tabType) {
        // Reset all tab buttons styling
        const tabs = document.querySelectorAll('#kurirFilterTabs .tab-btn');
        tabs.forEach(tab => {
            tab.classList.remove('bg-primary-container', 'text-on-primary', 'font-bold', 'shadow-sm');
            tab.classList.add('text-secondary', 'font-medium');
        });

        // Set active tab styling
        button.classList.remove('text-secondary', 'font-medium');
        button.classList.add('bg-primary-container', 'text-on-primary', 'font-bold', 'shadow-sm');

        const verifiedSection = document.getElementById('verifiedSection');
        const pendingSection = document.getElementById('pendingSection');

        if (tabType === 'unverified') {
            verifiedSection.classList.add('hidden');
            pendingSection.classList.remove('hidden');
        } else if (tabType === 'verified' || tabType === 'active') {
            verifiedSection.classList.remove('hidden');
            pendingSection.classList.add('hidden');
        } else {
            verifiedSection.classList.remove('hidden');
            pendingSection.classList.remove('hidden');
        }
    }

    function filterKurirTable() {
        const input = document.getElementById('searchInput');
        const filter = input.value.toLowerCase();
        const table = document.getElementById('courierTable');
        const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

        for (let i = 0; i < rows.length; i++) {
            const text = rows[i].textContent.toLowerCase();
            rows[i].style.display = text.indexOf(filter) > -1 ? '' : 'none';
        }
    }

    function openDriverModal(id) {
        const c = window.__couriers[id];
        if (!c) return;

        const modal = document.getElementById('driverDetailModal');
        document.getElementById('modalDriverName').textContent = c.name;
        document.getElementById('modalDriverAvatar').textContent = c.name.charAt(0).toUpperCase();
        document.getElementById('modalDriverEmailPhone').textContent = c.email + ' • ' + c.phone;
        document.getElementById('modalDriverId').textContent = 'ID Kurir: ' + c.idLabel;
        document.getElementById('modalVehicleType').textContent = c.vehicleLabel;
        document.getElementById('modalVehiclePlate').textContent = c.plate;

        const badge = document.getElementById('modalDriverBadge');
        if (c.verified) {
            badge.classList.remove('bg-surface-container', 'text-secondary');
            badge.classList.add('bg-emerald-50', 'text-emerald-700');
            badge.innerHTML = '<span class="material-symbols-outlined text-[13px]">verified</span> Terverifikasi';
        } else {
            badge.classList.remove('bg-emerald-50', 'text-emerald-700');
            badge.classList.add('bg-surface-container', 'text-secondary');
            badge.innerHTML = '<span class="material-symbols-outlined text-[13px]">hourglass_top</span> Menunggu Verifikasi';
        }

        document.getElementById('modalDocKtp').textContent = 'NIK: ' + c.nik;
        document.getElementById('modalDocKtpStatus').textContent = c.hasKtp ? 'Valid' : 'Belum';
        document.getElementById('modalDocKtpStatus').className = 'px-space-xs py-0.5 rounded font-label-sm text-label-sm font-semibold ' + (c.hasKtp ? 'bg-emerald-100 text-emerald-800' : 'bg-surface-container-highest text-secondary');
        document.getElementById('modalDocSim').textContent = c.hasSim ? 'Berlaku aktif' : 'Belum diunggah';
        document.getElementById('modalDocSimStatus').textContent = c.hasSim ? 'Valid' : 'Belum';
        document.getElementById('modalDocSimStatus').className = 'px-space-xs py-0.5 rounded font-label-sm text-label-sm font-semibold ' + (c.hasSim ? 'bg-emerald-100 text-emerald-800' : 'bg-surface-container-highest text-secondary');
        document.getElementById('modalDocVehicle').textContent = c.hasVehicle ? 'Foto kendaraan & plat sudah diunggah' : 'Belum ada foto kendaraan';
        document.getElementById('modalDocVehicleStatus').textContent = c.hasVehicle ? 'Valid' : 'Belum';
        document.getElementById('modalDocVehicleStatus').className = 'px-space-xs py-0.5 rounded font-label-sm text-label-sm font-semibold ' + (c.hasVehicle ? 'bg-emerald-100 text-emerald-800' : 'bg-surface-container-highest text-secondary');

        document.getElementById('modalWaLink').href = 'https://wa.me/' + c.wa;

        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeDriverModal() {
        const modal = document.getElementById('driverDetailModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function openAddCourierModal() {
        closeDriverModal();
        closeEditModal();
        const modal = document.getElementById('addCourierModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeAddCourierModal() {
        const modal = document.getElementById('addCourierModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function openEditModal(id) {
        const c = window.__couriers[id];
        if (!c) return;

        closeDriverModal();
        closeAddCourierModal();

        const form = document.getElementById('editCourierForm');
        form.action = `{{ url('admin/couriers') }}/${id}`;

        document.getElementById('editAvatar').textContent = c.name.charAt(0).toUpperCase();
        document.getElementById('editIdentityLabel').textContent = c.name;
        document.getElementById('editIdLabel').textContent = 'ID Kurir: ' + c.idLabel;
        document.getElementById('editName').value = c.name;
        document.getElementById('editPhone').value = c.phone === '-' ? '' : c.phone;
        document.getElementById('editNik').value = c.nikRaw || '';
        document.getElementById('editCity').value = c.city || '';
        document.getElementById('editVehicleType').value = c.vehicleType || 'motor';
        document.getElementById('editVehicleBrand').value = c.vehicleBrand || '';
        document.getElementById('editVehicleYear').value = c.vehicleYear || '';
        document.getElementById('editPlate').value = c.plate === '-' ? '' : c.plate;
        document.getElementById('editIsActive').checked = c.active;

        const modal = document.getElementById('editCourierModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeEditModal() {
        const modal = document.getElementById('editCourierModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function confirmSuspend(name) {
        return confirm(`Apakah Anda yakin ingin menangguhkan akun kurir ${name}? Tindakan ini akan menghentikan akses kurir ke aplikasi penerimaan pesanan.`);
    }

    function confirmSteer() {
        return confirm('Verifikasi berkas kurir ini sekarang? Kurir akan langsung aktif menerima pesanan.');
    }

    function shareRegistrationLink() {
        const url = `{{ url('/register') }}`;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(() => alert('Link pendaftaran kurir disalin: ' + url));
        } else {
            alert('Link pendaftaran kurir: ' + url);
        }
    }
</script>
@endpush