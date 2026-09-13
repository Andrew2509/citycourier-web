@extends('layouts.admin')

@section('content')
<div class="flex flex-col w-full gap-space-xl">
    <!-- Header Bar -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-space-md">
        <div>
            <div class="flex items-center gap-space-xs mb-space-2xs">
                <span class="font-label-sm text-label-sm uppercase tracking-wider text-primary font-bold">City-Work Dispatch Cockpit</span>
                <span class="w-1.5 h-1.5 rounded-full bg-primary"></span>
                <span class="font-label-sm text-label-sm text-secondary">Zona Operasional: Surabaya Pusat &amp; Timur</span>
            </div>
            <h1 class="font-headline-xl text-headline-xl text-on-surface font-bold tracking-tight">Ci-Work Dashboard Kerja</h1>
            <p class="font-body-md text-body-md text-secondary mt-space-2xs">Monitoring operasional kurir real-time dan dispatch tugas harian berbasis GPS</p>
        </div>
        <div class="flex items-center gap-space-sm self-start lg:self-auto">
            <button onclick="window.location.reload()" class="flex items-center gap-space-xs px-space-md py-space-xs bg-surface-container-lowest text-on-surface hover:bg-surface-container-high rounded-xl shadow-sm transition-all text-body-sm font-body-sm">
                <span class="material-symbols-outlined text-[18px] text-secondary">refresh</span>
                <span class="font-medium">Segarkan Data</span>
            </button>
        </div>
    </div>

    <!-- KPI Stat Metrics Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-space-md">
        <!-- Card 1: Kurir Online -->
        <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between relative overflow-hidden group hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between">
                <div>
                    <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Kurir Online</span>
                    <div class="flex items-baseline gap-space-xs mt-space-xs">
                        <span id="stat-online" class="font-display-lg text-display-lg text-on-surface font-bold font-data-mono">{{ $stats['online_couriers'] }}</span>
                        <span class="font-label-md text-label-md text-secondary">Armada Aktif</span>
                    </div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-secondary-container flex items-center justify-center text-on-secondary-container">
                    <span class="material-symbols-outlined text-[20px]">two_wheeler</span>
                </div>
            </div>
            <div class="mt-space-md pt-space-xs flex items-center justify-between">
                <span class="inline-flex items-center gap-1.5 px-space-xs py-space-2xs rounded-full bg-emerald-50 text-emerald-700 font-label-sm text-label-sm font-semibold">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    {{ $stats['online_couriers'] }} Siap Kerja
                </span>
            </div>
        </div>
        <!-- Card 2: Tugas Berjalan -->
        <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between relative overflow-hidden group hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between">
                <div>
                    <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Tugas Berjalan</span>
                    <div class="flex items-baseline gap-space-xs mt-space-xs">
                        <span id="stat-active" class="font-display-lg text-display-lg text-primary font-bold font-data-mono">{{ $stats['active_tasks'] }}</span>
                        <span class="font-label-md text-label-md text-secondary">Pengiriman</span>
                    </div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-primary-fixed flex items-center justify-center text-on-primary-fixed-variant">
                    <span class="material-symbols-outlined text-[20px]">local_shipping</span>
                </div>
            </div>
            <div class="mt-space-md pt-space-xs flex items-center justify-between">
                <span class="inline-flex items-center gap-1 px-space-xs py-space-2xs rounded-full bg-amber-50 text-amber-800 font-label-sm text-label-sm font-semibold">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                    Sedang Diantar
                </span>
            </div>
        </div>
        <!-- Card 3: Selesai Hari Ini -->
        <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between relative overflow-hidden group hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between">
                <div>
                    <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Selesai Hari Ini</span>
                    <div class="flex items-baseline gap-space-xs mt-space-xs">
                        <span id="stat-completed" class="font-display-lg text-display-lg text-on-surface font-bold font-data-mono">{{ $stats['completed_today'] }}</span>
                        <span class="font-label-md text-label-md text-secondary">Paket Drop-off</span>
                    </div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-surface-container-high flex items-center justify-center text-on-surface">
                    <span class="material-symbols-outlined text-[20px]">task_alt</span>
                </div>
            </div>
            <div class="mt-space-md pt-space-xs flex items-center justify-between">
                <span class="inline-flex items-center gap-1 px-space-xs py-space-2xs rounded-full bg-emerald-50 text-emerald-700 font-label-sm text-label-sm font-semibold">
                    <span class="material-symbols-outlined text-[14px]">check_circle</span>
                    100% On-Time
                </span>
            </div>
        </div>
        <!-- Card 4: Omzet Hari Ini -->
        <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between relative overflow-hidden group hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between">
                <div>
                    <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Omzet Hari Ini</span>
                    <div class="flex items-baseline gap-space-xs mt-space-xs">
                        <span id="stat-omzet" class="font-headline-xl text-headline-xl text-on-surface font-bold font-data-mono">Rp {{ number_format($stats['total_earnings_today'], 0, ',', '.') }}</span>
                    </div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-primary-fixed flex items-center justify-center text-on-primary-fixed-variant">
                    <span class="material-symbols-outlined text-[20px]">payments</span>
                </div>
            </div>
            <div class="mt-space-md pt-space-xs flex items-center justify-between">
                <span class="font-label-sm text-label-sm text-secondary font-medium">Bersih: <strong id="stat-omzet-net" class="text-on-surface font-data-mono">Rp {{ number_format($stats['total_earnings_today'] * 0.9, 0, ',', '.') }}</strong></span>
            </div>
        </div>
    </div>

    <!-- 12-Column Operational Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-space-xl">
        <!-- Left Column: Active Tasks (8 cols) -->
        <div class="lg:col-span-8 flex flex-col gap-space-xl">
            <!-- Live Task Card -->
            <div class="bg-surface-container-lowest rounded-xl shadow-sm p-space-lg flex flex-col">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-space-sm pb-space-md">
                    <div class="flex items-center gap-space-sm">
                        <div class="w-8 h-8 rounded-lg bg-primary-fixed flex items-center justify-center text-on-primary-fixed-variant">
                            <span class="material-symbols-outlined text-[18px]">radiology</span>
                        </div>
                        <div>
                            <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold">Tugas Aktif Terkini</h2>
                            <span id="task-count-label" class="font-label-sm text-label-sm text-secondary">{{ $stats['active_tasks'] }} Penugasan live terhubung GPS</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-space-xs">
                        <span class="inline-flex items-center gap-1 px-space-xs py-space-2xs rounded-md bg-surface-container-low text-secondary font-data-mono text-[12px]">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping"></span> Live Tracking<span id="liveUpdated"></span>
                        </span>
                        <a class="inline-flex items-center gap-1 font-label-md text-label-md text-primary hover:text-surface-tint font-semibold pl-space-xs" href="{{ route('admin.ci-work.tasks') }}">
                            Lihat Semua
                            <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                        </a>
                    </div>
                </div>

                <div id="activeTasksList">
                    @include('admin.ci-work.partials.active-tasks', ['recentTasks' => $recentTasks])
                </div>
            </div>
        </div>

        <!-- Right Column: Quick Links (4 cols) -->
        <div class="lg:col-span-4 flex flex-col gap-space-xl">
            <!-- Tautan Cepat -->
            <div class="bg-surface-container-lowest rounded-xl shadow-sm p-space-lg flex flex-col">
                <div class="flex items-center gap-space-sm pb-space-md mb-space-xs">
                    <div class="w-8 h-8 rounded-lg bg-primary-fixed flex items-center justify-center text-on-primary-fixed-variant">
                        <span class="material-symbols-outlined text-[18px]">link</span>
                    </div>
                    <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold">Tautan Cepat & Aksi</h2>
                </div>
                <div class="flex flex-col gap-space-sm">
                    <a href="{{ route('admin.ci-work.attendance') }}" class="group flex items-center justify-between p-space-md rounded-xl bg-surface-container-low hover:bg-surface-container transition-all">
                        <div class="flex items-center gap-space-sm">
                            <div class="w-9 h-9 rounded-lg bg-surface-container-lowest flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-on-primary transition-colors shadow-sm">
                                <span class="material-symbols-outlined text-[20px]">badge</span>
                            </div>
                            <div class="flex flex-col">
                                <span class="font-body-md text-body-md text-on-surface font-semibold group-hover:text-primary transition-colors">Cek Presensi Kurir</span>
                                <span class="font-label-sm text-label-sm text-secondary">Absensi harian</span>
                            </div>
                        </div>
                        <span class="material-symbols-outlined text-[18px] text-secondary group-hover:text-primary transition-colors">chevron_right</span>
                    </a>
                    <a href="{{ route('admin.ci-work.tasks') }}" class="group flex items-center justify-between p-space-md rounded-xl bg-surface-container-low hover:bg-surface-container transition-all">
                        <div class="flex items-center gap-space-sm">
                            <div class="w-9 h-9 rounded-lg bg-surface-container-lowest flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-on-primary transition-colors shadow-sm">
                                <span class="material-symbols-outlined text-[20px]">assignment</span>
                            </div>
                            <div class="flex flex-col">
                                <span class="font-body-md text-body-md text-on-surface font-semibold group-hover:text-primary transition-colors">Manajemen Tugas</span>
                                <span class="font-label-sm text-label-sm text-secondary">Dispatch & routing</span>
                            </div>
                        </div>
                        <span class="material-symbols-outlined text-[18px] text-secondary group-hover:text-primary transition-colors">chevron_right</span>
                    </a>
                    <a href="{{ route('admin.ci-work.finance') }}" class="group flex items-center justify-between p-space-md rounded-xl bg-surface-container-low hover:bg-surface-container transition-all">
                        <div class="flex items-center gap-space-sm">
                            <div class="w-9 h-9 rounded-lg bg-surface-container-lowest flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-on-primary transition-colors shadow-sm">
                                <span class="material-symbols-outlined text-[20px]">payments</span>
                            </div>
                            <div class="flex flex-col">
                                <span class="font-body-md text-body-md text-on-surface font-semibold group-hover:text-primary transition-colors">Keuangan & Setoran</span>
                                <span class="font-label-sm text-label-sm text-secondary">Rekap penghasilan</span>
                            </div>
                        </div>
                        <span class="material-symbols-outlined text-[18px] text-secondary group-hover:text-primary transition-colors">chevron_right</span>
                    </a>
                    <a href="{{ route('admin.couriers') }}" class="group flex items-center justify-between p-space-md rounded-xl bg-surface-container-low hover:bg-surface-container transition-all">
                        <div class="flex items-center gap-space-sm">
                            <div class="w-9 h-9 rounded-lg bg-surface-container-lowest flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-on-primary transition-colors shadow-sm">
                                <span class="material-symbols-outlined text-[20px]">two_wheeler</span>
                            </div>
                            <div class="flex flex-col">
                                <span class="font-body-md text-body-md text-on-surface font-semibold group-hover:text-primary transition-colors">Daftar Kurir</span>
                                <span class="font-label-sm text-label-sm text-secondary">Armada & verifikasi</span>
                            </div>
                        </div>
                        <span class="material-symbols-outlined text-[18px] text-secondary group-hover:text-primary transition-colors">chevron_right</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const url = '{{ route('admin.ci-work.tasks.refresh') }}';
    const rowsEl = document.getElementById('activeTasksList');
    const liveEl = document.getElementById('liveUpdated');
    if (!rowsEl) return;

    const setText = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    };

    window._ciDashPolling = true;

    setInterval(async () => {
        try {
            const res = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (!res.ok) return;
            const data = await res.json();

            if (data.rows !== undefined) rowsEl.innerHTML = data.rows;

            if (data.stats) {
                const nf = (n) => 'Rp ' + new Intl.NumberFormat('id-ID').format(n || 0);
                setText('stat-online', data.stats.online_couriers);
                setText('stat-active', data.stats.active_tasks);
                setText('stat-completed', data.stats.completed_today);
                setText('stat-omzet', nf(data.stats.total_earnings_today));
                setText('stat-omzet-net', nf((data.stats.total_earnings_today || 0) * 0.9));
                setText('task-count-label', data.stats.active_tasks + ' Penugasan live terhubung GPS');
                const chip = document.querySelector('#stat-active')?.closest('.bg-surface-container-lowest');
                if (chip) {
                    chip.classList.add('ring-2', 'ring-primary/40');
                    setTimeout(() => chip.classList.remove('ring-2', 'ring-primary/40'), 600);
                }
            }

            if (liveEl) liveEl.textContent = ' • ' + new Date().toLocaleTimeString('id-ID', { hour12: false });
        } catch (e) { /* polling berhenti sementara jika koneksi bermasalah */ }
    }, 8000);
})();
</script>
@endpush
