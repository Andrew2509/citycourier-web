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
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet"/>
    @vite('resources/css/admin.css')
    @stack('styles')
</head>
<body>
    @php
        $navActive = fn (bool $active) => $active ? 'nav-item is-active' : 'nav-item';
        $notifTotal = ($pendingOrders ?? 0) + ($pendingShipments ?? 0) + ($unverified ?? 0);
    @endphp

    <!-- Overlay sidebar (mobile) -->
    <div id="sidebarOverlay" class="fixed inset-0 z-40 hidden bg-slate-900/50 lg:hidden" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 flex w-sidebar-width -translate-x-full flex-col bg-navy-900 transition-transform duration-200 lg:translate-x-0">
        <div class="flex h-header-height shrink-0 items-center gap-2.5 border-b border-white/10 px-4">
            <img src="{{ asset('images/logo-city-courier.png') }}" alt="City Courier" class="h-8 w-auto max-w-[120px] object-contain shrink-0">
            <div class="min-w-0 leading-tight">
                <div class="text-[11px] text-slate-400">Admin Panel</div>
            </div>
            <button type="button" class="btn-icon ml-auto text-slate-400 hover:bg-white/10 hover:text-white lg:hidden" onclick="toggleSidebar()" aria-label="Tutup menu">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto px-2.5 pb-4">
            @canPerm('dashboard.view')
            <div class="nav-group">Menu Utama</div>
            <a href="{{ route('admin.dashboard') }}" class="{{ $navActive(request()->routeIs('admin.dashboard*')) }}">
                <span class="flex items-center gap-2.5"><span class="material-symbols-outlined">dashboard</span>Dashboard</span>
            </a>
            @endcanPerm

            <div class="nav-group">Manajemen</div>
            @canPerm('couriers.view')
            <a href="{{ route('admin.couriers', ['filter' => 'unverified']) }}" class="{{ $navActive(request()->routeIs('admin.couriers*') && request('filter') === 'unverified') }}">
                <span class="flex items-center gap-2.5"><span class="material-symbols-outlined">how_to_reg</span>Verifikasi Kurir</span>
                @if(($unverified ?? 0) > 0)
                    <span class="nav-badge bg-brand-600 text-white">{{ $unverified }}</span>
                @endif
            </a>
            <a href="{{ route('admin.couriers', ['filter' => 'verified']) }}" class="{{ $navActive(request()->routeIs('admin.couriers*') && request('filter') !== 'unverified') }}">
                <span class="flex items-center gap-2.5"><span class="material-symbols-outlined">two_wheeler</span>Daftar Kurir</span>
            </a>
            @endcanPerm
            @canPerm('orders.view')
            <a href="{{ route('admin.orders') }}" class="{{ $navActive(request()->routeIs('admin.orders*')) }}">
                <span class="flex items-center gap-2.5"><span class="material-symbols-outlined">inventory_2</span>Pesanan</span>
                @if(($pendingOrders ?? 0) > 0)
                    <span class="nav-badge bg-white/15 text-slate-200">{{ $pendingOrders }}</span>
                @endif
            </a>
            @endcanPerm
            @canPerm('shipments.view')
            <a href="{{ route('admin.shipments.index') }}" class="{{ $navActive(request()->routeIs('admin.shipments*')) }}">
                <span class="flex items-center gap-2.5"><span class="material-symbols-outlined">local_shipping</span>Pengiriman</span>
            </a>
            @endcanPerm
            @canPerm('drop_points.view')
            <a href="{{ route('admin.drop-points.index') }}" class="{{ $navActive(request()->routeIs('admin.drop-points*')) }}">
                <span class="flex items-center gap-2.5"><span class="material-symbols-outlined">warehouse</span>Drop Point</span>
            </a>
            @endcanPerm

            <div class="nav-group">City-Work Operasional</div>
            @canPerm('ci_work.view')
            <a href="{{ route('admin.ci-work.index') }}" class="{{ $navActive(request()->routeIs('admin.ci-work.index*')) }}">
                <span class="flex items-center gap-2.5"><span class="material-symbols-outlined">monitoring</span>Dashboard Kerja</span>
            </a>
            @endcanPerm
            @canPerm('ci_work_attendance.view')
            <a href="{{ route('admin.ci-work.attendance') }}" class="{{ $navActive(request()->routeIs('admin.ci-work.attendance*')) }}">
                <span class="flex items-center gap-2.5"><span class="material-symbols-outlined">badge</span>Presensi Kurir</span>
            </a>
            @endcanPerm
            @canPerm('ci_work_tasks.view')
            <a href="{{ route('admin.ci-work.tasks') }}" class="{{ $navActive(request()->routeIs('admin.ci-work.tasks*')) }}">
                <span class="flex items-center gap-2.5"><span class="material-symbols-outlined">assignment</span>Manajemen Tugas</span>
            </a>
            @endcanPerm
            @canPerm('ci_work_finance.view')
            <a href="{{ route('admin.ci-work.finance') }}" class="{{ $navActive(request()->routeIs('admin.ci-work.finance*')) }}">
                <span class="flex items-center gap-2.5"><span class="material-symbols-outlined">payments</span>Keuangan &amp; Setoran</span>
            </a>
            @endcanPerm

            <div class="nav-group">Keuangan &amp; Wallet</div>
            @canPerm('finance_dashboard.view')
            <a href="{{ route('admin.finance.index') }}" class="{{ $navActive(request()->routeIs('admin.finance.index')) }}">
                <span class="flex items-center gap-2.5"><span class="material-symbols-outlined">account_balance_wallet</span>Payment &amp; Wallet</span>
            </a>
            @endcanPerm
            @canPerm('finance_wallets.view')
            <a href="{{ route('admin.finance.wallets') }}" class="{{ $navActive(request()->routeIs('admin.finance.wallets*', 'admin.finance.reconciliation*')) }}">
                <span class="flex items-center gap-2.5"><span class="material-symbols-outlined">balance</span>Dompet &amp; Rekonsiliasi</span>
            </a>
            @endcanPerm
            @canPerm('finance_payouts.view')
            <a href="{{ route('admin.finance.payouts') }}" class="{{ $navActive(request()->routeIs('admin.finance.topups*', 'admin.finance.payments*', 'admin.finance.withdrawals*', 'admin.finance.earnings*', 'admin.finance.payouts*', 'admin.finance.refunds*', 'admin.finance.destinations*')) }}">
                <span class="flex items-center gap-2.5"><span class="material-symbols-outlined">currency_exchange</span>Top-Up, Payout &amp; Refund</span>
            </a>
            @endcanPerm
            @canPerm('finance_reports.view')
            <a href="{{ route('admin.finance.reports') }}" class="{{ $navActive(request()->routeIs('admin.finance.reports*', 'admin.finance.audit-logs*')) }}">
                <span class="flex items-center gap-2.5"><span class="material-symbols-outlined">summarize</span>Laporan &amp; Audit</span>
            </a>
            @endcanPerm
            @canPerm('finance_settings.view')
            <a href="{{ route('admin.finance.settings') }}" class="{{ $navActive(request()->routeIs('admin.finance.settings*')) }}">
                <span class="flex items-center gap-2.5"><span class="material-symbols-outlined">tune</span>Gateway &amp; Komisi</span>
            </a>
            @endcanPerm

            @canPerm('research_study.view')
            <div class="nav-group">Penelitian</div>
            <a href="{{ route('admin.research.index') }}" class="{{ $navActive(request()->routeIs('admin.research*') && !request()->routeIs('admin.research.show')) }}">
                <span class="flex items-center gap-2.5"><span class="material-symbols-outlined">science</span>Studi Pendahuluan</span>
            </a>
            <a href="{{ route('admin.research.index') }}#responden" class="nav-item">
                <span class="flex items-center gap-2.5"><span class="material-symbols-outlined">table_view</span>Data Responden</span>
            </a>
            <a href="{{ route('admin.research.index') }}#analisis" class="nav-item">
                <span class="flex items-center gap-2.5"><span class="material-symbols-outlined">analytics</span>Analisis Hasil</span>
            </a>
            @endcanPerm

            <div class="nav-group">Sistem &amp; Keamanan</div>
            @canPerm('users.view')
            <a href="{{ route('admin.users.index') }}" class="{{ $navActive(request()->routeIs('admin.users*')) }}">
                <span class="flex items-center gap-2.5"><span class="material-symbols-outlined">group</span>Manajemen User</span>
            </a>
            @endcanPerm
            @canPerm('roles.view')
            <a href="{{ route('admin.roles.index') }}" class="{{ $navActive(request()->routeIs('admin.roles*')) }}">
                <span class="flex items-center gap-2.5"><span class="material-symbols-outlined">admin_panel_settings</span>Manajemen Role</span>
            </a>
            @endcanPerm
            @canPerm('permissions.view')
            <a href="{{ route('admin.permissions.index') }}" class="{{ $navActive(request()->routeIs('admin.permissions*')) }}">
                <span class="flex items-center gap-2.5"><span class="material-symbols-outlined">lock_person</span>Manajemen Permission</span>
            </a>
            @endcanPerm
            @canPerm('settings.view')
            <a href="{{ route('admin.settings.providers') }}" class="{{ $navActive(request()->routeIs('admin.settings.*')) }}">
                <span class="flex items-center gap-2.5"><span class="material-symbols-outlined">hub</span>Provider &amp; Integrasi</span>
            </a>
            @endcanPerm
            @canPerm('documentation.view')
            <a href="{{ route('api.documentation') }}" target="_blank" rel="noopener" class="nav-item">
                <span class="flex items-center gap-2.5"><span class="material-symbols-outlined">code</span>Dokumentasi API</span>
                <span class="material-symbols-outlined !text-[15px]">open_in_new</span>
            </a>
            @endcanPerm

            @canPerm('app_download.view')
            @php $activeApk = \App\Models\AppDownload::getActive(); @endphp
            <div class="nav-group">Unduh Aplikasi</div>
            <a href="{{ route('admin.app-download') }}" class="{{ $navActive(request()->routeIs('admin.app-download*')) }}">
                <span class="flex items-center gap-2.5"><span class="material-symbols-outlined">install_mobile</span>Kelola APK</span>
            </a>
            <a href="{{ route('download.app') }}" class="nav-item">
                <span class="flex items-center gap-2.5"><span class="material-symbols-outlined">android</span>Download App</span>
                <span class="nav-badge bg-white/10 text-slate-300">v{{ $activeApk->version ?? '1.0.0' }}</span>
            </a>
            @endcanPerm
        </nav>
    </aside>

    <!-- Konten utama -->
    <div class="flex min-h-screen flex-col lg:pl-sidebar-width">
        <header class="sticky top-0 z-30 flex h-header-height items-center justify-between gap-4 border-b border-slate-200 bg-white px-4 sm:px-6">
            <div class="flex min-w-0 items-center gap-3">
                <button id="hamburgerBtn" type="button" class="btn-icon lg:hidden" aria-label="Buka menu">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <div class="flex min-w-0 items-center gap-1.5 text-[13px]">
                    <span class="hidden text-slate-400 sm:inline">Admin</span>
                    <span class="material-symbols-outlined hidden text-[16px] text-slate-300 sm:inline">chevron_right</span>
                    <span class="truncate font-medium text-slate-800">@yield('title', 'Dashboard')</span>
                </div>
            </div>

            <div class="flex items-center gap-1.5 sm:gap-2">
                {{-- Notifikasi (data pending nyata) --}}
                <div class="relative">
                    <button id="btnNotif" type="button" class="btn-icon relative" aria-label="Notifikasi" aria-haspopup="true" aria-expanded="false">
                        <span class="material-symbols-outlined">notifications</span>
                        @if($notifTotal > 0)
                            <span class="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-brand-600 px-0.5 text-[9px] font-bold text-white">
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
                                <span class="material-symbols-outlined text-amber-600">schedule</span>
                                <span class="flex-1 min-w-0">
                                    <span class="block font-medium text-slate-800">{{ $pendingOrders }} pesanan menunggu kurir</span>
                                    <span class="block text-[11.5px] text-slate-400">Perlu penugasan di menu Pesanan</span>
                                </span>
                            </a>
                            @endif
                            @if(($pendingShipments ?? 0) > 0)
                            <a href="{{ route('admin.shipments.index', ['status' => 'pending']) }}" class="topbar-menu-item">
                                <span class="material-symbols-outlined text-blue-600">local_shipping</span>
                                <span class="flex-1 min-w-0">
                                    <span class="block font-medium text-slate-800">{{ $pendingShipments }} pengiriman menunggu diproses</span>
                                    <span class="block text-[11.5px] text-slate-400">Permintaan pickup dari aplikasi pelanggan</span>
                                </span>
                            </a>
                            @endif
                            @if(($unverified ?? 0) > 0)
                            <a href="{{ route('admin.couriers', ['filter' => 'unverified']) }}" class="topbar-menu-item">
                                <span class="material-symbols-outlined text-brand-600">how_to_reg</span>
                                <span class="flex-1 min-w-0">
                                    <span class="block font-medium text-slate-800">{{ $unverified }} kurir menunggu verifikasi</span>
                                    <span class="block text-[11.5px] text-slate-400">Verifikasi dokumen kurir baru</span>
                                </span>
                            </a>
                            @endif
                            @if($notifTotal === 0)
                            <div class="px-4 py-6 text-center">
                                <span class="material-symbols-outlined mb-1 block text-[28px] text-slate-300">task_alt</span>
                                <div class="text-[13px] font-medium text-slate-700">Tidak ada notifikasi baru</div>
                                <div class="text-[11.5px] text-slate-400 mt-0.5">Semua item operasional telah ditangani.</div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                <a href="{{ route('download.app') }}" class="btn btn-secondary btn-sm hidden sm:inline-flex">
                    <span class="material-symbols-outlined">download</span>
                    Download App
                </a>

                <div class="hidden h-6 w-px bg-slate-200 sm:block"></div>

                {{-- Profil pengguna --}}
                <div class="relative">
                    <button id="btnProfile" type="button" class="flex items-center gap-2 rounded-md px-1 py-1 hover:bg-slate-100 transition-colors" aria-label="Menu profil" aria-haspopup="true" aria-expanded="false">
                        <div class="avatar bg-navy-900 text-white">
                            {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
                        </div>
                        <div class="hidden flex-col items-start leading-tight md:flex">
                            <span class="text-[13px] font-medium text-slate-800">{{ auth()->user()->name ?? 'Admin City Courier' }}</span>
                            <span class="text-[11.5px] text-slate-500">{{ auth()->user()?->roles?->first()?->name ? ucwords(str_replace('-', ' ', auth()->user()->roles->first()->name)) : 'Administrator' }}</span>
                        </div>
                        <span class="material-symbols-outlined hidden text-[18px] text-slate-400 md:inline">expand_more</span>
                    </button>

                    <div id="menuProfile" class="topbar-menu hidden w-60">
                        <div class="topbar-menu-header flex items-center gap-2.5">
                            <div class="avatar bg-navy-900 text-white">{{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}</div>
                            <div class="min-w-0 leading-tight">
                                <div class="truncate text-[13px] font-semibold text-slate-900">{{ auth()->user()->name ?? 'Admin City Courier' }}</div>
                                <div class="truncate text-[11.5px] text-slate-500">{{ auth()->user()->email ?? '' }}</div>
                            </div>
                        </div>
                        <div class="py-1.5">
                            @canPerm('dashboard.view')
                            <a href="{{ route('admin.dashboard') }}" class="topbar-menu-item">
                                <span class="material-symbols-outlined text-slate-400">dashboard</span>
                                Dashboard
                            </a>
                            @endcanPerm
                            @canPerm('settings.view')
                            <a href="{{ route('admin.settings.providers') }}" class="topbar-menu-item">
                                <span class="material-symbols-outlined text-slate-400">tune</span>
                                Pengaturan Sistem
                            </a>
                            @endcanPerm
                        </div>
                        <div class="topbar-menu-footer">
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="topbar-menu-item !px-2.5 rounded-md text-red-600 hover:bg-red-50">
                                    <span class="material-symbols-outlined">logout</span>
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
                        <span class="material-symbols-outlined">check_circle</span>
                        <span class="flex-1">{{ session('success') }}</span>
                        <button type="button" class="text-emerald-700 hover:text-emerald-900" onclick="this.parentElement.remove()" aria-label="Tutup">
                            <span class="material-symbols-outlined text-[18px]">close</span>
                        </button>
                    </div>
                @endif
                @if(session('error'))
                    <div id="flashError" class="alert alert-error">
                        <span class="material-symbols-outlined">error</span>
                        <span class="flex-1">{{ session('error') }}</span>
                        <button type="button" class="text-red-700 hover:text-red-900" onclick="this.parentElement.remove()" aria-label="Tutup">
                            <span class="material-symbols-outlined text-[18px]">close</span>
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

    function closeTopbarMenus(exceptId) {
        ['menuNotif', 'menuProfile'].forEach(function (id) {
            const menu = document.getElementById(id);
            if (menu && id !== exceptId) menu.classList.add('hidden');
        });
        ['btnNotif', 'btnProfile'].forEach(function (id) {
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

    document.addEventListener('DOMContentLoaded', function () {
        const hamburger = document.getElementById('hamburgerBtn');
        if (hamburger) hamburger.addEventListener('click', toggleSidebar);

        setupTopbarMenu('btnNotif', 'menuNotif');
        setupTopbarMenu('btnProfile', 'menuProfile');

        document.addEventListener('click', function (e) {
            if (!e.target.closest('#menuNotif, #btnNotif, #menuProfile, #btnProfile')) {
                closeTopbarMenus();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeTopbarMenus();
        });

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
