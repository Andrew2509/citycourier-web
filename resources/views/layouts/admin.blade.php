<!DOCTYPE html>
<html class="light" lang="id">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin Panel') - CityCourier</title>
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style>
        @layer base { html, body { margin: 0; padding: 0; } body { overscroll-behavior: none; } main > :first-child { margin-top: 0 !important; } main > :last-child { margin-bottom: 0 !important; } }
        ::-webkit-scrollbar { display: none; }
    </style>
    <script src="https://cdn.tailwindcss.com"></script>
    <script id="tailwind-config">
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                "colors": {
                    "secondary-fixed": "#dae2fd",
                    "on-primary": "#ffffff",
                    "outline": "#8c7164",
                    "on-secondary-fixed-variant": "#3f465c",
                    "on-tertiary-fixed": "#0d1c2e",
                    "on-error": "#ffffff",
                    "on-primary-fixed": "#341100",
                    "surface-tint": "#9d4300",
                    "primary": "#9d4300",
                    "surface-container-high": "#dce9ff",
                    "primary-fixed": "#ffdbca",
                    "surface-variant": "#d3e4fe",
                    "on-surface": "#0b1c30",
                    "surface-container-highest": "#d3e4fe",
                    "on-secondary-container": "#5c647a",
                    "primary-fixed-dim": "#ffb690",
                    "inverse-surface": "#213145",
                    "on-tertiary": "#ffffff",
                    "tertiary-container": "#8d9bb2",
                    "on-surface-variant": "#584237",
                    "error-container": "#ffdad6",
                    "secondary": "#565e74",
                    "on-background": "#0b1c30",
                    "on-primary-container": "#582200",
                    "on-secondary": "#ffffff",
                    "secondary-container": "#dae2fd",
                    "tertiary-fixed-dim": "#b9c7df",
                    "inverse-on-surface": "#eaf1ff",
                    "on-tertiary-container": "#253345",
                    "primary-container": "#f97316",
                    "on-secondary-fixed": "#131b2e",
                    "surface-container-lowest": "#ffffff",
                    "surface-bright": "#f8f9ff",
                    "tertiary": "#515f74",
                    "outline-variant": "#e0c0b1",
                    "on-tertiary-fixed-variant": "#3a485b",
                    "surface": "#f8f9ff",
                    "background": "#f8f9ff",
                    "on-primary-fixed-variant": "#783200",
                    "surface-container": "#e5eeff",
                    "error": "#ba1a1a",
                    "secondary-fixed-dim": "#bec6e0",
                    "tertiary-fixed": "#d5e3fc",
                    "on-error-container": "#93000a",
                    "inverse-primary": "#ffb690",
                    "surface-container-low": "#eff4ff",
                    "surface-dim": "#cbdbf5"
                },
                "borderRadius": {
                    "DEFAULT": "0.125rem",
                    "lg": "0.25rem",
                    "xl": "0.5rem",
                    "full": "0.75rem"
                },
                "spacing": {
                    "space-base": "1rem",
                    "gutter-table": "0.75rem",
                    "space-md": "0.75rem",
                    "space-xl": "1.5rem",
                    "space-xs": "0.25rem",
                    "sidebar-width": "16rem",
                    "space-lg": "1.25rem",
                    "space-sm": "0.5rem",
                    "space-2xs": "0.125rem",
                    "space-2xl": "2rem",
                    "sidebar-collapsed": "4.5rem",
                    "header-height": "4rem"
                },
                "fontFamily": {
                    "display-lg": ["Inter"],
                    "headline-sm": ["Inter"],
                    "data-mono": ["Inter"],
                    "body-lg": ["Inter"],
                    "label-sm": ["Inter"],
                    "body-sm": ["Inter"],
                    "body-md": ["Inter"],
                    "label-md": ["Inter"],
                    "headline-lg": ["Inter"],
                    "headline-xl": ["Inter"]
                },
                "fontSize": {
                    "display-lg": ["32px", { "lineHeight": "40px", "letterSpacing": "-0.02em", "fontWeight": "700" }],
                    "headline-sm": ["16px", { "lineHeight": "24px", "letterSpacing": "-0.005em", "fontWeight": "600" }],
                    "data-mono": ["13px", { "lineHeight": "18px", "letterSpacing": "-0.01em", "fontWeight": "500" }],
                    "body-lg": ["15px", { "lineHeight": "22px", "letterSpacing": "0em", "fontWeight": "400" }],
                    "label-sm": ["11px", { "lineHeight": "14px", "letterSpacing": "0.03em", "fontWeight": "600" }],
                    "body-sm": ["13px", { "lineHeight": "18px", "letterSpacing": "0.005em", "fontWeight": "400" }],
                    "body-md": ["14px", { "lineHeight": "20px", "letterSpacing": "0em", "fontWeight": "400" }],
                    "label-md": ["12px", { "lineHeight": "16px", "letterSpacing": "0.01em", "fontWeight": "500" }],
                    "headline-lg": ["20px", { "lineHeight": "28px", "letterSpacing": "-0.01em", "fontWeight": "600" }],
                    "headline-xl": ["24px", { "lineHeight": "32px", "letterSpacing": "-0.015em", "fontWeight": "600" }]
                }
            }
        }
    };
    </script>
    @stack('styles')
</head>
<body class="bg-background font-body-md text-body-md text-on-surface antialiased">

    <!-- Mobile Overlay -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <aside class="fixed left-0 top-0 h-full w-sidebar-width bg-surface-container-lowest z-50 flex flex-col shadow-[0_1px_8px_rgba(0,0,0,0.04)]">
        <!-- Logo -->
        <div class="h-header-height flex items-center px-space-xl gap-space-sm bg-surface-container-lowest shrink-0">
            <div class="w-8 h-8 rounded-lg bg-primary-container flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-on-primary text-[18px]">local_shipping</span>
            </div>
            <div class="flex flex-col">
                <span class="font-headline-sm text-headline-sm text-on-surface leading-tight tracking-tight">CityCourier</span>
                <span class="font-label-sm text-label-sm uppercase tracking-wider text-primary font-bold">Admin Panel</span>
            </div>
        </div>

        <!-- Navigation -->
        <div class="flex-1 overflow-y-auto px-space-md py-space-sm">
            <nav class="flex flex-col gap-space-xs" data-active-classes="bg-primary-container text-on-primary font-bold shadow-sm rounded-lg">

                <!-- Menu Utama -->
                <div class="px-space-sm pt-space-xs pb-space-2xs">
                    <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Menu Utama</span>
                </div>
                <a href="{{ route('admin.dashboard') }}" class="flex items-center justify-between px-space-md py-space-sm rounded-lg transition-all {{ request()->routeIs('admin.dashboard*') ? 'bg-primary-container text-on-primary font-bold shadow-sm rounded-lg' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}">
                    <div class="flex items-center gap-space-sm">
                        <span class="material-symbols-outlined text-[18px]">dashboard</span>
                        <span class="font-body-md text-body-md">Dashboard</span>
                    </div>
                </a>

                <!-- Manajemen -->
                <div class="px-space-sm pt-space-md pb-space-2xs">
                    <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Manajemen</span>
                </div>
                <a href="{{ route('admin.couriers', ['filter' => 'unverified']) }}" class="flex items-center justify-between px-space-md py-space-sm rounded-lg transition-all {{ request('filter') === 'unverified' ? 'bg-primary-container text-on-primary font-bold shadow-sm rounded-lg' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}">
                    <div class="flex items-center gap-space-sm">
                        <span class="material-symbols-outlined text-[18px]">how_to_reg</span>
                        <span class="font-body-md text-body-md">Verifikasi Kurir</span>
                    </div>
                    @if(($unverified ?? 0) > 0)
                        <span class="px-space-xs py-space-2xs rounded-lg font-label-sm text-label-sm bg-primary-fixed text-on-primary-fixed-variant font-bold">{{ $unverified }}</span>
                    @endif
                </a>
                <a href="{{ route('admin.couriers', ['filter' => 'verified']) }}" class="flex items-center justify-between px-space-md py-space-sm rounded-lg transition-all {{ request('filter') === 'verified' ? 'bg-primary-container text-on-primary font-bold shadow-sm rounded-lg' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}">
                    <div class="flex items-center gap-space-sm">
                        <span class="material-symbols-outlined text-[18px]">two_wheeler</span>
                        <span class="font-body-md text-body-md">Daftar Kurir</span>
                    </div>
                </a>
                <a href="{{ route('admin.orders') }}" class="flex items-center justify-between px-space-md py-space-sm rounded-lg transition-all {{ request()->routeIs('admin.orders*') ? 'bg-primary-container text-on-primary font-bold shadow-sm rounded-lg' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}">
                    <div class="flex items-center gap-space-sm">
                        <span class="material-symbols-outlined text-[18px]">inventory_2</span>
                        <span class="font-body-md text-body-md">Pesanan</span>
                    </div>
                    @if(($pendingOrders ?? 0) > 0)
                        <span class="px-space-xs py-space-2xs rounded-lg font-label-sm text-label-sm bg-secondary-container text-on-secondary-container font-bold">{{ $pendingOrders }}</span>
                    @endif
                </a>
                <a href="{{ route('admin.shipments.index') }}" class="flex items-center justify-between px-space-md py-space-sm rounded-lg transition-all {{ request()->routeIs('admin.shipments*') ? 'bg-primary-container text-on-primary font-bold shadow-sm rounded-lg' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}">
                    <div class="flex items-center gap-space-sm">
                        <span class="material-symbols-outlined text-[18px]">local_shipping</span>
                        <span class="font-body-md text-body-md">Pengiriman</span>
                    </div>
                </a>
                <a href="{{ route('admin.drop-points.index') }}" class="flex items-center justify-between px-space-md py-space-sm rounded-lg transition-all {{ request()->routeIs('admin.drop-points*') ? 'bg-primary-container text-on-primary font-bold shadow-sm rounded-lg' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}">
                    <div class="flex items-center gap-space-sm">
                        <span class="material-symbols-outlined text-[18px]">warehouse</span>
                        <span class="font-body-md text-body-md">Drop Point</span>
                    </div>
                </a>

                <!-- City-Work Operasional -->
                <div class="px-space-sm pt-space-md pb-space-2xs">
                    <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">City-Work Operasional</span>
                </div>
                <a href="{{ route('admin.ci-work.index') }}" class="flex items-center justify-between px-space-md py-space-sm rounded-lg transition-all {{ request()->routeIs('admin.ci-work.index*') ? 'bg-primary-container text-on-primary font-bold shadow-sm rounded-lg' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}">
                    <div class="flex items-center gap-space-sm">
                        <span class="material-symbols-outlined text-[18px]">monitoring</span>
                        <span class="font-body-md text-body-md">Dashboard Kerja</span>
                    </div>
                </a>
                <a href="{{ route('admin.ci-work.attendance') }}" class="flex items-center justify-between px-space-md py-space-sm rounded-lg transition-all {{ request()->routeIs('admin.ci-work.attendance*') ? 'bg-primary-container text-on-primary font-bold shadow-sm rounded-lg' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}">
                    <div class="flex items-center gap-space-sm">
                        <span class="material-symbols-outlined text-[18px]">badge</span>
                        <span class="font-body-md text-body-md">Presensi Kurir</span>
                    </div>
                </a>
                <a href="{{ route('admin.ci-work.tasks') }}" class="flex items-center justify-between px-space-md py-space-sm rounded-lg transition-all {{ request()->routeIs('admin.ci-work.tasks*') ? 'bg-primary-container text-on-primary font-bold shadow-sm rounded-lg' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}">
                    <div class="flex items-center gap-space-sm">
                        <span class="material-symbols-outlined text-[18px]">assignment</span>
                        <span class="font-body-md text-body-md">Manajemen Tugas</span>
                    </div>
                </a>
                <a href="{{ route('admin.ci-work.finance') }}" class="flex items-center justify-between px-space-md py-space-sm rounded-lg transition-all {{ request()->routeIs('admin.ci-work.finance*') ? 'bg-primary-container text-on-primary font-bold shadow-sm rounded-lg' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}">
                    <div class="flex items-center gap-space-sm">
                        <span class="material-symbols-outlined text-[18px]">payments</span>
                        <span class="font-body-md text-body-md">Keuangan &amp; Setoran</span>
                    </div>
                </a>

                <!-- Sistem & Keamanan -->
                <div class="px-space-sm pt-space-md pb-space-2xs">
                    <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Sistem &amp; Keamanan</span>
                </div>
                <a href="{{ route('admin.users.index') }}" class="flex items-center justify-between px-space-md py-space-sm rounded-lg transition-all {{ request()->routeIs('admin.users*') ? 'bg-primary-container text-on-primary font-bold shadow-sm rounded-lg' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}">
                    <div class="flex items-center gap-space-sm">
                        <span class="material-symbols-outlined text-[18px]">group</span>
                        <span class="font-body-md text-body-md">Manajemen User</span>
                    </div>
                </a>
                <a href="{{ route('admin.roles.index') }}" class="flex items-center justify-between px-space-md py-space-sm rounded-lg transition-all {{ request()->routeIs('admin.roles*') ? 'bg-primary-container text-on-primary font-bold shadow-sm rounded-lg' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}">
                    <div class="flex items-center gap-space-sm">
                        <span class="material-symbols-outlined text-[18px]">admin_panel_settings</span>
                        <span class="font-body-md text-body-md">Manajemen Role</span>
                    </div>
                </a>
                <a href="{{ route('admin.permissions.index') }}" class="flex items-center justify-between px-space-md py-space-sm rounded-lg transition-all {{ request()->routeIs('admin.permissions*') ? 'bg-primary-container text-on-primary font-bold shadow-sm rounded-lg' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}">
                    <div class="flex items-center gap-space-sm">
                        <span class="material-symbols-outlined text-[18px]">lock_person</span>
                        <span class="font-body-md text-body-md">Manajemen Permission</span>
                    </div>
                </a>
                <a href="{{ route('admin.settings.providers') }}" class="flex items-center justify-between px-space-md py-space-sm rounded-lg transition-all {{ request()->routeIs('admin.settings.*') ? 'bg-primary-container text-on-primary font-bold shadow-sm rounded-lg' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}">
                    <div class="flex items-center gap-space-sm">
                        <span class="material-symbols-outlined text-[18px]">hub</span>
                        <span class="font-body-md text-body-md">Provider &amp; Integrasi</span>
                    </div>
                </a>
                <a href="{{ route('api.documentation') }}" target="_blank" rel="noopener" class="flex items-center justify-between px-space-md py-space-sm rounded-lg transition-all text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface">
                    <div class="flex items-center gap-space-sm">
                        <span class="material-symbols-outlined text-[18px]">code</span>
                        <span class="font-body-md text-body-md">Dokumentasi API</span>
                    </div>
                    <span class="material-symbols-outlined text-[14px] text-secondary">open_in_new</span>
                </a>

                <!-- Unduh Aplikasi -->
                @php
                    $activeApk = \App\Models\AppDownload::getActive();
                @endphp
                <div class="px-space-sm pt-space-md pb-space-2xs">
                    <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Unduh Aplikasi</span>
                </div>
                <a href="{{ route('admin.app-download') }}" class="flex items-center justify-between px-space-md py-space-sm rounded-lg transition-all {{ request()->routeIs('admin.app-download*') ? 'bg-primary-container text-on-primary font-bold shadow-sm rounded-lg' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}">
                    <div class="flex items-center gap-space-sm">
                        <span class="material-symbols-outlined text-[18px]">install_mobile</span>
                        <span class="font-body-md text-body-md">Kelola APK</span>
                    </div>
                </a>
                <a href="{{ route('download.app') }}" class="flex items-center justify-between px-space-md py-space-sm rounded-lg transition-all bg-surface-container-low text-on-surface hover:bg-surface-container-high hover:text-on-surface">
                    <div class="flex items-center gap-space-sm">
                        <span class="material-symbols-outlined text-[18px] text-primary">android</span>
                        <span class="font-body-md text-body-md">Download App</span>
                    </div>
                    <span class="px-space-xs py-space-2xs rounded-lg font-label-sm text-label-sm bg-primary-container text-on-primary font-bold">v{{ $activeApk->version ?? '1.0.0' }}</span>
                </a>
            </nav>
        </div>

        <!-- Gateway Status Footer -->
        <div class="p-space-md bg-surface-container-lowest shrink-0">
            <div class="flex items-center gap-space-sm p-space-sm rounded-lg bg-surface-container-low">
                <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                <div class="flex flex-col">
                    <span class="font-label-sm text-label-sm text-on-surface font-semibold">Gateway Server: Active</span>
                    <span class="font-label-sm text-label-sm text-secondary font-data-mono">Latency 24ms &bull; 99.9%</span>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="pl-sidebar-width">
        <!-- Header -->
        <header class="fixed top-0 left-sidebar-width right-0 h-header-height bg-surface-container-lowest/90 backdrop-blur-xl z-40 flex items-center justify-between px-space-xl shadow-[0_1px_8px_rgba(0,0,0,0.04)]">
            <div class="flex items-center gap-space-md flex-1 max-w-xl">
                <button id="hamburgerBtn" class="lg:hidden p-space-xs text-on-surface hover:bg-surface-container-high rounded-lg transition-colors">
                    <span class="material-symbols-outlined text-[22px]">menu</span>
                </button>
                <div class="relative w-full flex items-center">
                    <span class="material-symbols-outlined absolute left-space-md text-secondary pointer-events-none text-[20px]">search</span>
                    <input class="w-full bg-surface pl-10 pr-16 py-space-xs h-9 rounded-lg font-body-sm text-body-sm text-on-surface placeholder:text-secondary focus:outline-none focus:bg-surface-container-lowest shadow-[0_1px_3px_0_rgba(15,23,42,0.04)]" placeholder="Cari pesanan, kurir, atau resi..." type="text"/>
                    <div class="absolute right-space-sm flex items-center gap-space-2xs pointer-events-none">
                        <kbd class="px-space-xs py-space-2xs rounded-lg bg-surface-container-high text-on-surface font-data-mono text-[10px] font-bold">&#8984;K</kbd>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-space-lg">
                <a href="{{ route('download.app') }}" class="flex items-center gap-space-xs px-space-md py-space-xs h-9 bg-primary-container hover:bg-primary text-on-primary font-label-md text-label-md rounded-lg shadow-sm transition-all">
                    <span class="material-symbols-outlined text-[18px]">download</span>
                    <span>Download App</span>
                </a>
                <button class="relative p-space-xs text-secondary hover:text-on-surface hover:bg-surface-container-high rounded-lg transition-colors" type="button">
                    <span class="material-symbols-outlined text-[22px]">notifications</span>
                    <span class="absolute top-1 right-1 w-2 h-2 rounded-full bg-error"></span>
                </button>
                <div class="h-6 w-px bg-surface-container-highest"></div>
                <div class="flex items-center gap-space-md">
                    <div class="w-8 h-8 rounded-full bg-primary-container text-on-primary flex items-center justify-center font-bold text-sm shadow-sm">
                        {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
                    </div>
                    <div class="hidden md:flex flex-col text-left">
                        <span class="font-label-md text-label-md text-on-surface font-semibold leading-tight">{{ auth()->user()->name ?? 'Admin City Courier' }}</span>
                        <span class="font-label-sm text-label-sm text-secondary leading-tight">Administrator</span>
                    </div>
                </div>
                <form action="{{ route('logout') }}" method="POST" style="margin:0;">
                    @csrf
                    <button type="submit" class="p-space-xs text-secondary hover:text-error hover:bg-error-container/20 rounded-lg transition-colors" title="Keluar / Logout">
                        <span class="material-symbols-outlined text-[20px]">logout</span>
                    </button>
                </form>
            </div>
        </header>

        <!-- Content -->
        <main class="w-full pt-header-height bg-surface min-h-screen px-space-xl py-space-xl">
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

    document.addEventListener('DOMContentLoaded', function() {
        const hamburger = document.getElementById('hamburgerBtn');
        if (hamburger) {
            hamburger.addEventListener('click', toggleSidebar);
        }
        // Auto-dismiss flash messages
        ['flashSuccess', 'flashError'].forEach(id => {
            const el = document.getElementById(id);
            if (el) setTimeout(() => { el.style.transition = 'opacity 0.5s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 500); }, 4000);
        });
    });
    </script>
    @stack('scripts')
</body>
</html>
