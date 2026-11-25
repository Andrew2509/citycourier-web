<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin Panel') - CityCourier</title>
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    @vite(['resources/css/admin.css', 'resources/js/admin-ui.js'])
    <script>
    // Kelas awal animasi entrance. Fallback: bila bundle JS gagal dimuat,
    // kelas dilepas agar seluruh UI tampil pada state final.
    document.documentElement.classList.add('cc-anim');
    // Sembunyikan teks nama ikon (placeholder Lucide) sampai runtime konversi aktif.
    document.documentElement.classList.add('cc-icons-pending');
    window.addEventListener('load', function () {
        setTimeout(function () {
            if (!window.__ccReady) document.documentElement.classList.remove('cc-anim');
            if (!window.__ccIconsDone) document.documentElement.classList.remove('cc-icons-pending');
        }, 3000);
    });
    </script>
    @stack('styles')
</head>
<body>
    @php
        $navActive = fn (bool $active) => $active ? 'nav-item is-active' : 'nav-item';
        $notifTotal = ($pendingOrders ?? 0) + ($pendingShipments ?? 0) + ($unverified ?? 0);
    @endphp

    <!-- Overlay sidebar (mobile) -->
    <div id="sidebarOverlay" class="fixed inset-0 z-40 hidden bg-ink-950/50 lg:hidden" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="-translate-x-full">
        <div class="flex h-header-height shrink-0 items-center gap-2.5 border-b border-slate-200 px-3.5">
            <img src="{{ asset('images/logo-city-courier.png') }}" alt="City Courier" class="h-8 w-auto max-w-[32px] shrink-0 object-contain">
            <div class="sidebar-brand min-w-0 leading-tight">
                <div class="truncate text-[14px] font-semibold text-ink-900">City Courier</div>
                <div class="text-[10.5px] font-medium tracking-wide text-slate-400">Admin Panel</div>
            </div>
            <button type="button" class="btn-icon ml-auto text-slate-400 hover:bg-slate-100 hover:text-ink-900 lg:hidden" onclick="toggleSidebar()" aria-label="Tutup menu">
                <span class="cc-icon">close</span>
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto px-2.5 pb-4">
            @canPerm('dashboard.view')
            <div class="nav-group">Menu Utama</div>
            <a href="{{ route('admin.dashboard') }}" data-tooltip="Dashboard" class="{{ $navActive(request()->routeIs('admin.dashboard*')) }}">
                <span class="flex min-w-0 items-center gap-2.5"><span class="cc-icon">dashboard</span><span class="nav-label">Dashboard</span></span>
            </a>
            @endcanPerm

            <div class="nav-group">Manajemen</div>
            @canPerm('couriers.view')
            <a href="{{ route('admin.couriers', ['filter' => 'unverified']) }}" data-tooltip="Verifikasi Kurir" class="{{ $navActive(request()->routeIs('admin.couriers*') && request('filter') === 'unverified') }}">
                <span class="flex min-w-0 items-center gap-2.5"><span class="cc-icon">how_to_reg</span><span class="nav-label">Verifikasi Kurir</span></span>
                @if(($unverified ?? 0) > 0)
                    <span class="nav-badge bg-brand-600 text-white">{{ $unverified }}</span>
                @endif
            </a>
            <a href="{{ route('admin.couriers', ['filter' => 'verified']) }}" data-tooltip="Daftar Kurir" class="{{ $navActive(request()->routeIs('admin.couriers*') && request('filter') !== 'unverified') }}">
                <span class="flex min-w-0 items-center gap-2.5"><span class="cc-icon">two_wheeler</span><span class="nav-label">Daftar Kurir</span></span>
            </a>
            @endcanPerm
            @canPerm('orders.view')
            <a href="{{ route('admin.orders') }}" data-tooltip="Pesanan" class="{{ $navActive(request()->routeIs('admin.orders*')) }}">
                <span class="flex min-w-0 items-center gap-2.5"><span class="cc-icon">inventory_2</span><span class="nav-label">Pesanan</span></span>
                @if(($pendingOrders ?? 0) > 0)
                    <span class="nav-badge bg-slate-200 text-slate-600">{{ $pendingOrders }}</span>
                @endif
            </a>
            @endcanPerm
            @canPerm('shipments.view')
            <a href="{{ route('admin.shipments.index') }}" data-tooltip="Pengiriman" class="{{ $navActive(request()->routeIs('admin.shipments*')) }}">
                <span class="flex min-w-0 items-center gap-2.5"><span class="cc-icon">local_shipping</span><span class="nav-label">Pengiriman</span></span>
            </a>
            @endcanPerm
            @canPerm('drop_points.view')
            <a href="{{ route('admin.drop-points.index') }}" data-tooltip="Drop Point" class="{{ $navActive(request()->routeIs('admin.drop-points*')) }}">
                <span class="flex min-w-0 items-center gap-2.5"><span class="cc-icon">warehouse</span><span class="nav-label">Drop Point</span></span>
            </a>
            @endcanPerm

            <div class="nav-group">City-Work Operasional</div>
            @canPerm('ci_work.view')
            <a href="{{ route('admin.ci-work.index') }}" data-tooltip="Dashboard Kerja" class="{{ $navActive(request()->routeIs('admin.ci-work.index*')) }}">
                <span class="flex min-w-0 items-center gap-2.5"><span class="cc-icon">monitoring</span><span class="nav-label">Dashboard Kerja</span></span>
            </a>
            @endcanPerm
            @canPerm('ci_work_attendance.view')
            <a href="{{ route('admin.ci-work.attendance') }}" data-tooltip="Presensi Kurir" class="{{ $navActive(request()->routeIs('admin.ci-work.attendance*')) }}">
                <span class="flex min-w-0 items-center gap-2.5"><span class="cc-icon">badge</span><span class="nav-label">Presensi Kurir</span></span>
            </a>
            @endcanPerm
            @canPerm('ci_work_tasks.view')
            <a href="{{ route('admin.ci-work.tasks') }}" data-tooltip="Manajemen Tugas" class="{{ $navActive(request()->routeIs('admin.ci-work.tasks*')) }}">
                <span class="flex min-w-0 items-center gap-2.5"><span class="cc-icon">assignment</span><span class="nav-label">Manajemen Tugas</span></span>
            </a>
            @endcanPerm
            @canPerm('ci_work_finance.view')
            <a href="{{ route('admin.ci-work.finance') }}" data-tooltip="Keuangan & Setoran" class="{{ $navActive(request()->routeIs('admin.ci-work.finance*')) }}">
                <span class="flex min-w-0 items-center gap-2.5"><span class="cc-icon">payments</span><span class="nav-label">Keuangan &amp; Setoran</span></span>
            </a>
            @endcanPerm

            <div class="nav-group">Keuangan &amp; Wallet</div>
            @canPerm('finance_dashboard.view')
            <a href="{{ route('admin.finance.index') }}" data-tooltip="Payment & Wallet" class="{{ $navActive(request()->routeIs('admin.finance.index')) }}">
                <span class="flex min-w-0 items-center gap-2.5"><span class="cc-icon">account_balance_wallet</span><span class="nav-label">Payment &amp; Wallet</span></span>
            </a>
            @endcanPerm
            @canPerm('finance_wallets.view')
            <a href="{{ route('admin.finance.wallets') }}" data-tooltip="Dompet & Rekonsiliasi" class="{{ $navActive(request()->routeIs('admin.finance.wallets*', 'admin.finance.reconciliation*')) }}">
                <span class="flex min-w-0 items-center gap-2.5"><span class="cc-icon">balance</span><span class="nav-label">Dompet &amp; Rekonsiliasi</span></span>
            </a>
            @endcanPerm
            @canPerm('finance_payouts.view')
            <a href="{{ route('admin.finance.payouts') }}" data-tooltip="Top-Up, Payout & Refund" class="{{ $navActive(request()->routeIs('admin.finance.topups*', 'admin.finance.payments*', 'admin.finance.withdrawals*', 'admin.finance.earnings*', 'admin.finance.payouts*', 'admin.finance.refunds*', 'admin.finance.destinations*')) }}">
                <span class="flex min-w-0 items-center gap-2.5"><span class="cc-icon">currency_exchange</span><span class="nav-label">Top-Up, Payout &amp; Refund</span></span>
            </a>
            @endcanPerm
            @canPerm('finance_reports.view')
            <a href="{{ route('admin.finance.reports') }}" data-tooltip="Laporan & Audit" class="{{ $navActive(request()->routeIs('admin.finance.reports*', 'admin.finance.audit-logs*')) }}">
                <span class="flex min-w-0 items-center gap-2.5"><span class="cc-icon">summarize</span><span class="nav-label">Laporan &amp; Audit</span></span>
            </a>
            @endcanPerm
            @canPerm('finance_settings.view')
            <a href="{{ route('admin.finance.settings') }}" data-tooltip="Gateway & Komisi" class="{{ $navActive(request()->routeIs('admin.finance.settings*')) }}">
                <span class="flex min-w-0 items-center gap-2.5"><span class="cc-icon">tune</span><span class="nav-label">Gateway &amp; Komisi</span></span>
            </a>
            @endcanPerm

            @canPerm('research_study.view')
            <div class="nav-group">Penelitian</div>
            <a href="{{ route('admin.research.index') }}" data-tooltip="Studi Pendahuluan" class="{{ $navActive(request()->routeIs('admin.research*') && !request()->routeIs('admin.research.show')) }}">
                <span class="flex min-w-0 items-center gap-2.5"><span class="cc-icon">science</span><span class="nav-label">Studi Pendahuluan</span></span>
            </a>
            <a href="{{ route('admin.research.index') }}#responden" data-tooltip="Data Responden" class="nav-item">
                <span class="flex min-w-0 items-center gap-2.5"><span class="cc-icon">table_view</span><span class="nav-label">Data Responden</span></span>
            </a>
            <a href="{{ route('admin.research.index') }}#analisis" data-tooltip="Analisis Hasil" class="nav-item">
                <span class="flex min-w-0 items-center gap-2.5"><span class="cc-icon">analytics</span><span class="nav-label">Analisis Hasil</span></span>
            </a>
            @endcanPerm

            <div class="nav-group">Sistem &amp; Keamanan</div>
            @canPerm('users.view')
            <a href="{{ route('admin.users.index') }}" data-tooltip="Manajemen User" class="{{ $navActive(request()->routeIs('admin.users*')) }}">
                <span class="flex min-w-0 items-center gap-2.5"><span class="cc-icon">group</span><span class="nav-label">Manajemen User</span></span>
            </a>
            @endcanPerm
            @canPerm('roles.view')
            <a href="{{ route('admin.roles.index') }}" data-tooltip="Manajemen Role" class="{{ $navActive(request()->routeIs('admin.roles*')) }}">
                <span class="flex min-w-0 items-center gap-2.5"><span class="cc-icon">admin_panel_settings</span><span class="nav-label">Manajemen Role</span></span>
            </a>
            @endcanPerm
            @canPerm('permissions.view')
            <a href="{{ route('admin.permissions.index') }}" data-tooltip="Manajemen Permission" class="{{ $navActive(request()->routeIs('admin.permissions*')) }}">
                <span class="flex min-w-0 items-center gap-2.5"><span class="cc-icon">lock_person</span><span class="nav-label">Manajemen Permission</span></span>
            </a>
            @endcanPerm
            @canPerm('settings.view')
            <a href="{{ route('admin.settings.providers') }}" data-tooltip="Provider & Integrasi" class="{{ $navActive(request()->routeIs('admin.settings.*')) }}">
                <span class="flex min-w-0 items-center gap-2.5"><span class="cc-icon">hub</span><span class="nav-label">Provider &amp; Integrasi</span></span>
            </a>
            @endcanPerm
            @canPerm('documentation.view')
            <a href="{{ route('api.documentation') }}" target="_blank" rel="noopener" data-tooltip="Dokumentasi API" class="nav-item">
                <span class="flex min-w-0 items-center gap-2.5"><span class="cc-icon">code</span><span class="nav-label">Dokumentasi API</span></span>
                <span class="cc-icon !text-[15px]">open_in_new</span>
            </a>
            @endcanPerm

            @canPerm('app_download.view')
            @php $activeApk = \App\Models\AppDownload::getActive(); @endphp
            <div class="nav-group">Unduh Aplikasi</div>
            <a href="{{ route('admin.app-download') }}" data-tooltip="Kelola APK" class="{{ $navActive(request()->routeIs('admin.app-download*')) }}">
                <span class="flex min-w-0 items-center gap-2.5"><span class="cc-icon">install_mobile</span><span class="nav-label">Kelola APK</span></span>
            </a>
            <a href="{{ route('download.app') }}" data-tooltip="Download App" class="nav-item">
                <span class="flex min-w-0 items-center gap-2.5"><span class="cc-icon">android</span><span class="nav-label">Download App</span></span>
                <span class="nav-badge bg-slate-100 text-slate-500">v{{ $activeApk->version ?? '1.0.0' }}</span>
            </a>
            @endcanPerm
        </nav>

        <div class="shrink-0 border-t border-slate-200 p-2.5">
            <button type="button" id="btnCollapse" class="nav-item w-full" aria-label="Ciutkan sidebar">
                <span class="flex min-w-0 items-center gap-2.5"><span class="cc-icon">chevron_left</span><span class="nav-label">Ciutkan Sidebar</span></span>
            </button>
        </div>
    </aside>

    <!-- Konten utama -->
    <div class="admin-main flex flex-col">
        <header class="sticky top-0 z-30 flex h-header-height items-center justify-between gap-4 border-b border-slate-200 bg-white px-4 sm:px-6">
            <div class="flex min-w-0 items-center gap-3">
                <button id="hamburgerBtn" type="button" class="btn-icon lg:hidden" aria-label="Buka menu">
                    <span class="cc-icon">menu</span>
                </button>
                <nav class="flex min-w-0 items-center gap-1.5 text-[13px]" aria-label="Breadcrumb">
                    <span class="hidden text-slate-400 sm:inline">Admin</span>
                    <span class="cc-icon hidden text-[14px] text-slate-300 sm:inline">chevron_right</span>
                    <span class="truncate font-medium text-ink-900">@yield('title', 'Dashboard')</span>
                </nav>

                <!-- Global search (command palette dari item menu sidebar) -->
                <div class="relative ml-2 hidden lg:block">
                    <div class="search-box">
                        <span class="cc-icon">search</span>
                        <input id="globalSearch" type="search" autocomplete="off" placeholder="Cari menu... (Ctrl+K)"
                            class="form-input h-8 w-64 !pl-9 text-[13px]" aria-label="Cari menu" />
                    </div>
                    <div id="searchResults" class="topbar-menu hidden w-80 max-h-80 overflow-y-auto"></div>
                </div>
            </div>

            <div class="flex items-center gap-1.5 sm:gap-2">
                {{-- Notifikasi (data pending nyata) --}}
                <div class="relative">
                    <button id="btnNotif" type="button" class="btn-icon relative" aria-label="Notifikasi" aria-haspopup="true" aria-expanded="false">
                        <span class="cc-icon">notifications</span>
                        @if($notifTotal > 0)
                            <span class="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-brand-500 px-0.5 text-[9px] font-bold text-white">
                                {{ $notifTotal > 9 ? '9+' : $notifTotal }}
                            </span>
                        @endif
                    </button>

                    <div id="menuNotif" class="topbar-menu hidden">
                        <div class="topbar-menu-header">
                            <div class="topbar-menu-title">Notifikasi</div>
                            <div class="text-[11.5px] text-slate-500">Item operasional yang menunggu tindakan</div>
                        </div>
                        <div class="py-1.5">
                            @if(($pendingOrders ?? 0) > 0)
                            <a href="{{ route('admin.orders', ['status' => 'pending']) }}" class="topbar-menu-item">
                                <span class="cc-icon text-amber-600">schedule</span>
                                <span class="flex-1 min-w-0">
                                    <span class="block font-medium text-ink-900">{{ $pendingOrders }} pesanan menunggu kurir</span>
                                    <span class="block text-[11.5px] text-slate-400">Perlu penugasan di menu Pesanan</span>
                                </span>
                            </a>
                            @endif
                            @if(($pendingShipments ?? 0) > 0)
                            <a href="{{ route('admin.shipments.index', ['status' => 'pending']) }}" class="topbar-menu-item">
                                <span class="cc-icon text-primary-600">local_shipping</span>
                                <span class="flex-1 min-w-0">
                                    <span class="block font-medium text-ink-900">{{ $pendingShipments }} pengiriman menunggu diproses</span>
                                    <span class="block text-[11.5px] text-slate-400">Permintaan pickup dari aplikasi pelanggan</span>
                                </span>
                            </a>
                            @endif
                            @if(($unverified ?? 0) > 0)
                            <a href="{{ route('admin.couriers', ['filter' => 'unverified']) }}" class="topbar-menu-item">
                                <span class="cc-icon text-brand-600">how_to_reg</span>
                                <span class="flex-1 min-w-0">
                                    <span class="block font-medium text-ink-900">{{ $unverified }} kurir menunggu verifikasi</span>
                                    <span class="block text-[11.5px] text-slate-400">Verifikasi dokumen kurir baru</span>
                                </span>
                            </a>
                            @endif
                            @if($notifTotal === 0)
                            <div class="px-4 py-6 text-center">
                                <span class="cc-icon mb-1 block text-[28px] text-slate-300">task_alt</span>
                                <div class="text-[13px] font-medium text-ink-800">Tidak ada notifikasi baru</div>
                                <div class="text-[11.5px] text-slate-400 mt-0.5">Semua item operasional telah ditangani.</div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Help center --}}
                <div class="relative">
                    <button id="btnHelp" type="button" class="btn-icon" aria-label="Bantuan" aria-haspopup="true" aria-expanded="false">
                        <span class="cc-icon">help</span>
                    </button>
                    <div id="menuHelp" class="topbar-menu hidden w-64">
                        <div class="topbar-menu-header">
                            <div class="topbar-menu-title">Pusat Bantuan</div>
                            <div class="text-[11.5px] text-slate-500">Referensi & dukungan sistem</div>
                        </div>
                        <div class="py-1.5">
                            @canPerm('documentation.view')
                            <a href="{{ route('api.documentation') }}" target="_blank" rel="noopener" class="topbar-menu-item">
                                <span class="cc-icon text-slate-400">code</span>
                                <span class="flex-1">Dokumentasi API</span>
                                <span class="cc-icon !text-[15px] text-slate-300">open_in_new</span>
                            </a>
                            @endcanPerm
                            <a href="{{ route('download.app') }}" class="topbar-menu-item">
                                <span class="cc-icon text-slate-400">android</span>
                                <span class="flex-1">Download aplikasi kurir</span>
                            </a>
                        </div>
                    </div>
                </div>

                <a href="{{ route('download.app') }}" class="btn btn-secondary btn-sm hidden sm:inline-flex">
                    <span class="cc-icon">download</span>
                    Download App
                </a>

                <div class="hidden h-6 w-px bg-slate-200 sm:block"></div>

                {{-- Profil pengguna --}}
                <div class="relative">
                    <button id="btnProfile" type="button" class="flex items-center gap-2 rounded-md px-1 py-1 hover:bg-slate-100 transition-colors" aria-label="Menu profil" aria-haspopup="true" aria-expanded="false">
                        <div class="avatar avatar-dark">
                            {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
                        </div>
                        <div class="hidden flex-col items-start leading-tight md:flex">
                            <span class="text-[13px] font-medium text-ink-900">{{ auth()->user()->name ?? 'Admin City Courier' }}</span>
                            <span class="text-[11.5px] text-slate-500">{{ auth()->user()?->roles?->first()?->name ? ucwords(str_replace('-', ' ', auth()->user()->roles->first()->name)) : 'Administrator' }}</span>
                        </div>
                        <span class="cc-icon hidden text-[18px] text-slate-400 md:inline">expand_more</span>
                    </button>

                    <div id="menuProfile" class="topbar-menu hidden w-60">
                        <div class="topbar-menu-header flex items-center gap-2.5">
                            <div class="avatar avatar-dark">{{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}</div>
                            <div class="min-w-0 leading-tight">
                                <div class="truncate text-[13px] font-semibold text-ink-900">{{ auth()->user()->name ?? 'Admin City Courier' }}</div>
                                <div class="truncate text-[11.5px] text-slate-500">{{ auth()->user()->email ?? '' }}</div>
                            </div>
                        </div>
                        <div class="py-1.5">
                            @canPerm('dashboard.view')
                            <a href="{{ route('admin.dashboard') }}" class="topbar-menu-item">
                                <span class="cc-icon text-slate-400">dashboard</span>
                                Dashboard
                            </a>
                            @endcanPerm
                            @canPerm('settings.view')
                            <a href="{{ route('admin.settings.providers') }}" class="topbar-menu-item">
                                <span class="cc-icon text-slate-400">tune</span>
                                Pengaturan Sistem
                            </a>
                            @endcanPerm
                        </div>
                        <div class="topbar-menu-footer">
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="topbar-menu-item !px-2.5 rounded-md text-red-600 hover:bg-red-50">
                                    <span class="cc-icon">logout</span>
                                    Keluar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="w-full flex-1 px-4 py-5 sm:px-6 lg:px-8">
            <div class="mx-auto flex w-full max-w-[1400px] flex-col gap-5">
                @if(session('success'))
                    <div id="flashSuccess" class="alert alert-success">
                        <span class="cc-icon">check_circle</span>
                        <span class="flex-1">{{ session('success') }}</span>
                        <button type="button" class="text-emerald-700 hover:text-emerald-900" onclick="this.parentElement.remove()" aria-label="Tutup">
                            <span class="cc-icon text-[18px]">close</span>
                        </button>
                    </div>
                @endif
                @if(session('error'))
                    <div id="flashError" class="alert alert-error">
                        <span class="cc-icon">error</span>
                        <span class="flex-1">{{ session('error') }}</span>
                        <button type="button" class="text-red-700 hover:text-red-900" onclick="this.parentElement.remove()" aria-label="Tutup">
                            <span class="cc-icon text-[18px]">close</span>
                        </button>
                    </div>
                @endif
                @if($errors->any())
                    <div id="flashValidationErrors" class="alert alert-error" role="alert">
                        <span class="cc-icon">warning</span>
                        <div class="flex-1">
                            <div class="font-semibold mb-1">Data belum tersimpan karena {{ $errors->count() }} kesalahan validasi:</div>
                            <ul class="list-disc pl-5 space-y-0.5 text-xs">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                        <button type="button" class="text-red-700 hover:text-red-900" onclick="this.parentElement.remove()" aria-label="Tutup">
                            <span class="cc-icon text-[18px]">close</span>
                        </button>
                    </div>
                @endif

                @yield('content')
            </div>
        </main>
    </div>

    <script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if (sidebar) sidebar.classList.toggle('-translate-x-full');
        if (overlay) overlay.classList.toggle('hidden');
    }

    function setSidebarCollapsed(collapsed) {
        const sidebar = document.getElementById('sidebar');
        if (!sidebar) return;
        sidebar.classList.toggle('is-collapsed', collapsed);
        document.body.classList.toggle('sidebar-collapsed', collapsed);
        try { localStorage.setItem('ccSidebarCollapsed', collapsed ? '1' : '0'); } catch (e) {}
        const btn = document.getElementById('btnCollapse');
        if (btn) {
            const icon = btn.querySelector('.cc-icon');
            if (icon) icon.style.transform = collapsed ? 'rotate(180deg)' : '';
            const label = btn.querySelector('.nav-label');
            if (label) label.textContent = collapsed ? 'Perluas Sidebar' : 'Ciutkan Sidebar';
        }
    }

    function closeTopbarMenus(exceptId) {
        ['menuNotif', 'menuProfile', 'menuHelp'].forEach(function (id) {
            const menu = document.getElementById(id);
            if (menu && id !== exceptId) menu.classList.add('hidden');
        });
        ['btnNotif', 'btnProfile', 'btnHelp'].forEach(function (id) {
            const btn = document.getElementById(id);
            if (btn && id !== exceptId) btn.setAttribute('aria-expanded', 'false');
        });
    }

    function setupTopbarMenu(btnId, menuId) {
        const btn = document.getElementById(btnId);
        const menu = document.getElementById(menuId);
        if (!btn || !menu) return;
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            const willOpen = menu.classList.contains('hidden');
            closeTopbarMenus(menuId);
            menu.classList.toggle('hidden', !willOpen);
            btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });
    }

    function setupGlobalSearch() {
        const input = document.getElementById('globalSearch');
        const results = document.getElementById('searchResults');
        if (!input || !results) return;

        const items = Array.from(document.querySelectorAll('#sidebar nav a.nav-item')).map(function (link) {
            const labelEl = link.querySelector('.nav-label');
            const group = link.parentElement
                ? Array.from(link.parentElement.children).reverse().find(function (el) { return el.classList && el.classList.contains('nav-group'); })
                : null;
            return {
                label: labelEl ? labelEl.textContent.trim() : link.textContent.trim(),
                group: group ? group.textContent.trim() : '',
                href: link.getAttribute('href'),
            };
        });

        function render(q) {
            const query = (q || '').toLowerCase().trim();
            if (!query) { results.classList.add('hidden'); results.innerHTML = ''; return; }
            const matches = items.filter(function (it) {
                return (it.label + ' ' + it.group).toLowerCase().includes(query);
            }).slice(0, 8);
            results.innerHTML = matches.length
                ? matches.map(function (it, i) {
                    return '<a href="' + it.href + '" class="topbar-menu-item' + (i === 0 ? ' is-first' : '') + '" data-search-item>' +
                        '<span class="cc-icon text-slate-400">search</span>' +
                        '<span class="flex-1 min-w-0"><span class="block font-medium text-[13px] text-[#172033]">' + it.label + '</span>' +
                        '<span class="block text-[11.5px] text-slate-400">' + it.group + '</span></span></a>';
                }).join('')
                : '<div class="px-4 py-5 text-center text-[12.5px] text-slate-400">Tidak ada menu yang cocok.</div>';
            results.classList.remove('hidden');
        }

        input.addEventListener('input', function () { render(input.value); });
        input.addEventListener('focus', function () { if (input.value.trim()) render(input.value); });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                const first = results.querySelector('[data-search-item]');
                if (first) window.location.href = first.getAttribute('href');
            } else if (e.key === 'Escape') {
                results.classList.add('hidden');
                input.blur();
            }
        });
        document.addEventListener('click', function (e) {
            if (!e.target.closest('#globalSearch, #searchResults')) results.classList.add('hidden');
        });
        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                input.focus();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const hamburger = document.getElementById('hamburgerBtn');
        if (hamburger) hamburger.addEventListener('click', toggleSidebar);

        setupTopbarMenu('btnNotif', 'menuNotif');
        setupTopbarMenu('btnProfile', 'menuProfile');
        setupTopbarMenu('btnHelp', 'menuHelp');
        setupGlobalSearch();

        document.addEventListener('click', function (e) {
            if (!e.target.closest('#menuNotif, #btnNotif, #menuProfile, #btnProfile, #menuHelp, #btnHelp')) {
                closeTopbarMenus();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeTopbarMenus();
        });

        // Collapse sidebar (desktop) — state persist di localStorage
        let saved = null;
        try { saved = localStorage.getItem('ccSidebarCollapsed'); } catch (e) {}
        if (saved === '1') setSidebarCollapsed(true);
        const collapseBtn = document.getElementById('btnCollapse');
        if (collapseBtn) {
            collapseBtn.addEventListener('click', function () {
                setSidebarCollapsed(!document.getElementById('sidebar').classList.contains('is-collapsed'));
            });
        }

        // Tutup drawer sidebar setelah menu diklik pada layar kecil
        document.querySelectorAll('#sidebar nav a').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth < 1024) {
                    const sidebar = document.getElementById('sidebar');
                    const overlay = document.getElementById('sidebarOverlay');
                    if (sidebar && !sidebar.classList.contains('-translate-x-full')) toggleSidebar();
                }
            });
        });

        ['flashSuccess', 'flashError'].forEach(function (id) {
            const el = document.getElementById(id);
            if (el) setTimeout(function () {
                el.style.transition = 'opacity .3s';
                el.style.opacity = '0';
                setTimeout(function () { el.remove(); }, 300);
            }, 5000);
        });
    });
    </script>
    @stack('scripts')
</body>
</html>
