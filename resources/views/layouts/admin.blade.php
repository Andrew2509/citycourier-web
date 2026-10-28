<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin Panel') - CityCourier</title>

    <!-- Google Fonts: Plus Jakarta Sans & Inter & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet"/>

    <script src="https://cdn.tailwindcss.com"></script>
    <script id="tailwind-config">
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                colors: {
                    // City Courier Brand Colors
                    brand: {
                        50: '#fff7ed',
                        100: '#ffedd5',
                        200: '#fed7aa',
                        300: '#fdba74',
                        400: '#fb923c',
                        500: '#f97316',
                        600: '#ec5b13',
                        700: '#c2410c',
                        800: '#9a3412',
                        900: '#7c2d12',
                    },
                    navy: {
                        800: '#1e293b',
                        900: '#0f172a',
                        950: '#0b132b',
                    },
                    // Backward Compatibility Aliases for Views
                    "primary": "#ec5b13",
                    "primary-container": "#ec5b13",
                    "on-primary": "#ffffff",
                    "primary-fixed": "#ffedd5",
                    "on-primary-fixed-variant": "#9a3412",
                    "secondary": "#64748b",
                    "on-secondary": "#ffffff",
                    "secondary-container": "#f1f5f9",
                    "on-secondary-container": "#334155",
                    "secondary-fixed": "#e2e8f0",
                    "on-secondary-fixed-variant": "#1e293b",
                    "surface": "#f8fafc",
                    "background": "#f8fafc",
                    "on-surface": "#0f172a",
                    "on-surface-variant": "#475569",
                    "surface-container-lowest": "#ffffff",
                    "surface-container-low": "#f8fafc",
                    "surface-container": "#f1f5f9",
                    "surface-container-high": "#e2e8f0",
                    "surface-container-highest": "#cbd5e1",
                    "outline": "#cbd5e1",
                    "outline-variant": "#e2e8f0",
                    "error": "#ef4444",
                    "error-container": "#fee2e2",
                    "on-error": "#ffffff",
                    "on-error-container": "#991b1b"
                },
                borderRadius: {
                    "DEFAULT": "0.375rem",
                    "sm": "0.25rem",
                    "md": "0.375rem",
                    "lg": "0.5rem",
                    "xl": "0.75rem",
                    "2xl": "1rem",
                    "full": "9999px"
                },
                spacing: {
                    "space-2xs": "0.125rem",
                    "space-xs": "0.25rem",
                    "space-sm": "0.5rem",
                    "space-md": "0.75rem",
                    "space-lg": "1rem",
                    "space-xl": "1.5rem",
                    "space-2xl": "2rem",
                    "sidebar-width": "16.5rem",
                    "header-height": "4rem"
                },
                fontFamily: {
                    sans: ['Inter', 'sans-serif'],
                    display: ['Plus Jakarta Sans', 'sans-serif'],
                    mono: ['JetBrains Mono', 'monospace'],
                    "headline-xl": ['Plus Jakarta Sans', 'sans-serif'],
                    "headline-lg": ['Plus Jakarta Sans', 'sans-serif'],
                    "headline-sm": ['Plus Jakarta Sans', 'sans-serif'],
                    "display-lg": ['Plus Jakarta Sans', 'sans-serif'],
                    "body-lg": ['Inter', 'sans-serif'],
                    "body-md": ['Inter', 'sans-serif'],
                    "body-sm": ['Inter', 'sans-serif'],
                    "label-md": ['Inter', 'sans-serif'],
                    "label-sm": ['Inter', 'sans-serif'],
                    "data-mono": ['JetBrains Mono', 'monospace']
                }
            }
        }
    };
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .font-display { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
        
        /* Custom scrollbar for sidebar */
        .sidebar-scroll::-webkit-scrollbar {
            width: 4px;
        }
        .sidebar-scroll::-webkit-scrollbar-track {
            background: rgba(15, 23, 42, 0.4);
        }
        .sidebar-scroll::-webkit-scrollbar-thumb {
            background: rgba(100, 116, 139, 0.4);
            border-radius: 4px;
        }
        .sidebar-scroll::-webkit-scrollbar-thumb:hover {
            background: rgba(100, 116, 139, 0.7);
        }
    </style>
    @stack('styles')
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen">

    <!-- Mobile Sidebar Backdrop Overlay -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-40 hidden lg:hidden transition-opacity" onclick="toggleSidebar()"></div>

    <!-- Master Sidebar -->
    <aside id="adminSidebar" class="fixed left-0 top-0 h-full w-[16.5rem] bg-navy-950 text-slate-300 z-50 flex flex-col border-r border-slate-800 shadow-xl -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out">
        <!-- Brand Header -->
        <div class="h-16 flex items-center justify-between px-5 bg-navy-950/90 border-b border-slate-800/80 shrink-0">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 group">
                <div class="w-9 h-9 rounded-xl bg-brand-600 flex items-center justify-center text-white shadow-md shadow-brand-600/30 group-hover:scale-105 transition-transform">
                    <span class="material-symbols-outlined text-[20px]">local_shipping</span>
                </div>
                <div class="flex flex-col">
                    <span class="font-display font-bold text-white text-base tracking-tight leading-tight">CityCourier</span>
                    <span class="font-mono text-[10px] font-semibold uppercase tracking-wider text-brand-500">Ops Console</span>
                </div>
            </a>
            <button class="lg:hidden p-1.5 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 transition-colors" onclick="toggleSidebar()">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        <!-- Navigation Scroll Area -->
        <div class="flex-1 overflow-y-auto px-3.5 py-4 sidebar-scroll">
            <nav class="space-y-6">

                <!-- GRUP 1: OPERASIONAL -->
                <div>
                    <div class="px-3 pb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400 font-mono">
                        Operasional
                    </div>
                    <div class="space-y-1">
                        @canPerm('dashboard.view')
                        <a href="{{ route('admin.dashboard') }}"
                           class="flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('admin.dashboard*') ? 'bg-slate-800/90 text-white font-semibold border-l-4 border-brand-500 shadow-sm' : 'text-slate-400 hover:bg-slate-800/50 hover:text-slate-200' }}">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-[20px] {{ request()->routeIs('admin.dashboard*') ? 'text-brand-500' : 'text-slate-400' }}">dashboard</span>
                                <span>Dashboard Utama</span>
                            </div>
                        </a>
                        @endcanPerm

                        @canPerm('orders.view')
                        <a href="{{ route('admin.orders') }}"
                           class="flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('admin.orders*') ? 'bg-slate-800/90 text-white font-semibold border-l-4 border-brand-500 shadow-sm' : 'text-slate-400 hover:bg-slate-800/50 hover:text-slate-200' }}">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-[20px] {{ request()->routeIs('admin.orders*') ? 'text-brand-500' : 'text-slate-400' }}">inventory_2</span>
                                <span>Pesanan Masuk</span>
                            </div>
                            @if(($pendingOrders ?? 0) > 0)
                                <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-brand-500/20 text-brand-400 border border-brand-500/30">{{ $pendingOrders }}</span>
                            @endif
                        </a>
                        @endcanPerm

                        @canPerm('shipments.view')
                        <a href="{{ route('admin.shipments.index') }}"
                           class="flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('admin.shipments*') ? 'bg-slate-800/90 text-white font-semibold border-l-4 border-brand-500 shadow-sm' : 'text-slate-400 hover:bg-slate-800/50 hover:text-slate-200' }}">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-[20px] {{ request()->routeIs('admin.shipments*') ? 'text-brand-500' : 'text-slate-400' }}">local_shipping</span>
                                <span>Pengiriman Real-time</span>
                            </div>
                        </a>
                        @endcanPerm

                        @canPerm('drop_points.view')
                        <a href="{{ route('admin.drop-points.index') }}"
                           class="flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('admin.drop-points*') ? 'bg-slate-800/90 text-white font-semibold border-l-4 border-brand-500 shadow-sm' : 'text-slate-400 hover:bg-slate-800/50 hover:text-slate-200' }}">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-[20px] {{ request()->routeIs('admin.drop-points*') ? 'text-brand-500' : 'text-slate-400' }}">warehouse</span>
                                <span>Drop Points</span>
                            </div>
                        </a>
                        @endcanPerm
                    </div>
                </div>

                <!-- GRUP 2: ARMADA KURIR -->
                @canPerm('couriers.view')
                <div>
                    <div class="px-3 pb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400 font-mono">
                        Armada Kurir
                    </div>
                    <div class="space-y-1">
                        <a href="{{ route('admin.couriers', ['filter' => 'unverified']) }}"
                           class="flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-medium transition-all {{ request('filter') === 'unverified' ? 'bg-slate-800/90 text-white font-semibold border-l-4 border-brand-500 shadow-sm' : 'text-slate-400 hover:bg-slate-800/50 hover:text-slate-200' }}">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-[20px] {{ request('filter') === 'unverified' ? 'text-brand-500' : 'text-slate-400' }}">how_to_reg</span>
                                <span>Verifikasi Berkas</span>
                            </div>
                            @if(($unverified ?? 0) > 0)
                                <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-amber-500/20 text-amber-400 border border-amber-500/30">{{ $unverified }}</span>
                            @endif
                        </a>

                        <a href="{{ route('admin.couriers', ['filter' => 'verified']) }}"
                           class="flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-medium transition-all {{ (request()->routeIs('admin.couriers*') && request('filter') !== 'unverified') ? 'bg-slate-800/90 text-white font-semibold border-l-4 border-brand-500 shadow-sm' : 'text-slate-400 hover:bg-slate-800/50 hover:text-slate-200' }}">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-[20px] {{ (request()->routeIs('admin.couriers*') && request('filter') !== 'unverified') ? 'text-brand-500' : 'text-slate-400' }}">two_wheeler</span>
                                <span>Daftar Armada Kurir</span>
                            </div>
                        </a>
                    </div>
                </div>
                @endcanPerm

                <!-- GRUP 3: CITY-WORK OPERASIONAL -->
                @canPerm('ci_work.view')
                <div>
                    <div class="px-3 pb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400 font-mono">
                        City-Work Lapangan
                    </div>
                    <div class="space-y-1">
                        <a href="{{ route('admin.ci-work.index') }}"
                           class="flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('admin.ci-work.index*') ? 'bg-slate-800/90 text-white font-semibold border-l-4 border-brand-500 shadow-sm' : 'text-slate-400 hover:bg-slate-800/50 hover:text-slate-200' }}">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-[20px] {{ request()->routeIs('admin.ci-work.index*') ? 'text-brand-500' : 'text-slate-400' }}">monitoring</span>
                                <span>Dashboard Lapangan</span>
                            </div>
                        </a>

                        @canPerm('ci_work_attendance.view')
                        <a href="{{ route('admin.ci-work.attendance') }}"
                           class="flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('admin.ci-work.attendance*') ? 'bg-slate-800/90 text-white font-semibold border-l-4 border-brand-500 shadow-sm' : 'text-slate-400 hover:bg-slate-800/50 hover:text-slate-200' }}">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-[20px] {{ request()->routeIs('admin.ci-work.attendance*') ? 'text-brand-500' : 'text-slate-400' }}">badge</span>
                                <span>Presensi &amp; Posisi</span>
                            </div>
                        </a>
                        @endcanPerm

                        @canPerm('ci_work_tasks.view')
                        <a href="{{ route('admin.ci-work.tasks') }}"
                           class="flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('admin.ci-work.tasks*') ? 'bg-slate-800/90 text-white font-semibold border-l-4 border-brand-500 shadow-sm' : 'text-slate-400 hover:bg-slate-800/50 hover:text-slate-200' }}">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-[20px] {{ request()->routeIs('admin.ci-work.tasks*') ? 'text-brand-500' : 'text-slate-400' }}">assignment</span>
                                <span>Manajemen Tugas</span>
                            </div>
                        </a>
                        @endcanPerm

                        @canPerm('ci_work_finance.view')
                        <a href="{{ route('admin.ci-work.finance') }}"
                           class="flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('admin.ci-work.finance*') ? 'bg-slate-800/90 text-white font-semibold border-l-4 border-brand-500 shadow-sm' : 'text-slate-400 hover:bg-slate-800/50 hover:text-slate-200' }}">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-[20px] {{ request()->routeIs('admin.ci-work.finance*') ? 'text-brand-500' : 'text-slate-400' }}">payments</span>
                                <span>Keuangan &amp; Setoran</span>
                            </div>
                        </a>
                        @endcanPerm
                    </div>
                </div>
                @endcanPerm

                <!-- GRUP 4: SISTEM & PENGATURAN -->
                <div>
                    <div class="px-3 pb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400 font-mono">
                        Sistem &amp; Integrasi
                    </div>
                    <div class="space-y-1">
                        @canPerm('settings.view')
                        <a href="{{ route('admin.settings.providers') }}"
                           class="flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('admin.settings.*') ? 'bg-slate-800/90 text-white font-semibold border-l-4 border-brand-500 shadow-sm' : 'text-slate-400 hover:bg-slate-800/50 hover:text-slate-200' }}">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-[20px] {{ request()->routeIs('admin.settings.*') ? 'text-brand-500' : 'text-slate-400' }}">hub</span>
                                <span>Provider &amp; WhatsApp</span>
                            </div>
                        </a>
                        @endcanPerm

                        @canPerm('users.view')
                        <a href="{{ route('admin.users.index') }}"
                           class="flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('admin.users*') ? 'bg-slate-800/90 text-white font-semibold border-l-4 border-brand-500 shadow-sm' : 'text-slate-400 hover:bg-slate-800/50 hover:text-slate-200' }}">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-[20px] {{ request()->routeIs('admin.users*') ? 'text-brand-500' : 'text-slate-400' }}">group</span>
                                <span>Manajemen User</span>
                            </div>
                        </a>
                        @endcanPerm

                        @canPerm('roles.view')
                        <a href="{{ route('admin.roles.index') }}"
                           class="flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('admin.roles*') ? 'bg-slate-800/90 text-white font-semibold border-l-4 border-brand-500 shadow-sm' : 'text-slate-400 hover:bg-slate-800/50 hover:text-slate-200' }}">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-[20px] {{ request()->routeIs('admin.roles*') ? 'text-brand-500' : 'text-slate-400' }}">admin_panel_settings</span>
                                <span>Role &amp; Hak Akses</span>
                            </div>
                        </a>
                        @endcanPerm

                        @canPerm('app_download.view')
                        <a href="{{ route('admin.app-download') }}"
                           class="flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('admin.app-download*') ? 'bg-slate-800/90 text-white font-semibold border-l-4 border-brand-500 shadow-sm' : 'text-slate-400 hover:bg-slate-800/50 hover:text-slate-200' }}">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-[20px] {{ request()->routeIs('admin.app-download*') ? 'text-brand-500' : 'text-slate-400' }}">install_mobile</span>
                                <span>Kelola Rilis APK</span>
                            </div>
                        </a>
                        @endcanPerm

                        @canPerm('documentation.view')
                        <a href="{{ route('api.documentation') }}" target="_blank" rel="noopener"
                           class="flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-medium text-slate-400 hover:bg-slate-800/50 hover:text-slate-200 transition-all">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-[20px] text-slate-400">code</span>
                                <span>Dokumentasi API</span>
                            </div>
                            <span class="material-symbols-outlined text-[16px] text-slate-400">open_in_new</span>
                        </a>
                        @endcanPerm
                    </div>
                </div>

            </nav>
        </div>

        <!-- Sidebar Profile & Logout Footer -->
        <div class="p-3.5 bg-navy-950 border-t border-slate-800/80 shrink-0">
            <div class="flex items-center justify-between p-2 rounded-xl bg-slate-900 border border-slate-800/80">
                <div class="flex items-center gap-3 overflow-hidden">
                    <div class="w-9 h-9 rounded-xl bg-brand-600 text-white font-bold flex items-center justify-center text-sm shadow-sm shrink-0">
                        {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
                    </div>
                    <div class="flex flex-col truncate">
                        <span class="text-xs font-semibold text-white truncate">{{ auth()->user()->name ?? 'Administrator' }}</span>
                        <span class="text-[11px] text-slate-400 truncate">{{ auth()->user()->email ?? 'admin@citycourier.com' }}</span>
                    </div>
                </div>

                <form action="{{ route('logout') }}" method="POST" class="m-0" onsubmit="return confirm('Apakah Anda yakin ingin keluar dari Admin Panel?')">
                    @csrf
                    <button type="submit" class="p-2 text-slate-400 hover:text-red-400 hover:bg-red-500/10 rounded-lg transition-colors" title="Keluar">
                        <span class="material-symbols-outlined text-[20px]">logout</span>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="lg:pl-[16.5rem] flex flex-col min-h-screen">

        <!-- Top Header Bar -->
        <header class="sticky top-0 z-30 h-16 bg-white/95 backdrop-blur-md border-b border-slate-200/80 px-4 sm:px-6 lg:px-8 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-3 sm:gap-4 flex-1">
                <!-- Mobile Hamburger Toggle -->
                <button class="lg:hidden p-2 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-xl transition-colors" onclick="toggleSidebar()" aria-label="Buka Menu">
                    <span class="material-symbols-outlined text-[24px]">menu</span>
                </button>

                <!-- Page / Context Breadcrumb -->
                <div class="flex items-center gap-2 text-xs sm:text-sm text-slate-500">
                    <span class="font-semibold text-slate-800 font-display">City Courier</span>
                    <span class="text-slate-300">/</span>
                    <span class="text-slate-600 font-medium">@yield('title', 'Admin Console')</span>
                </div>
            </div>

            <!-- Header Right Actions -->
            <div class="flex items-center gap-2.5 sm:gap-3">
                <!-- Real-time Clock / Date (Desktop) -->
                <div class="hidden md:flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-50 border border-slate-200/80 text-xs text-slate-600 font-medium">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span id="currentDateDisplay">Live Ops</span>
                </div>

                @php
                    $activeApk = \App\Models\AppDownload::getActive();
                @endphp
                @if($activeApk)
                <a href="{{ route('download.app') }}"
                   class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200/80 text-slate-700 text-xs font-semibold transition-colors border border-slate-200/80">
                    <span class="material-symbols-outlined text-[16px] text-brand-600">android</span>
                    <span>APK v{{ $activeApk->version }}</span>
                </a>
                @endif

                <!-- User Badge -->
                <div class="flex items-center gap-2.5 pl-2 border-l border-slate-200">
                    <div class="w-8 h-8 rounded-full bg-slate-900 text-white flex items-center justify-center font-bold text-xs shadow-sm">
                        {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
                    </div>
                    <span class="hidden xl:inline text-xs font-semibold text-slate-700">{{ auth()->user()->name ?? 'Admin' }}</span>
                </div>
            </div>
        </header>

        <!-- Main Body Container -->
        <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto">
            <!-- Flash Message Success -->
            @if(session('success'))
                <div id="flashSuccess" class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 flex items-center justify-between shadow-sm transition-all duration-300">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-emerald-600 text-[22px]">check_circle</span>
                        <span class="text-sm font-semibold">{{ session('success') }}</span>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="text-emerald-700 hover:text-emerald-900">
                        <span class="material-symbols-outlined text-[18px]">close</span>
                    </button>
                </div>
            @endif

            <!-- Flash Message Error -->
            @if(session('error'))
                <div id="flashError" class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-900 flex items-center justify-between shadow-sm transition-all duration-300">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-red-600 text-[22px]">error</span>
                        <span class="text-sm font-semibold">{{ session('error') }}</span>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="text-red-700 hover:text-red-900">
                        <span class="material-symbols-outlined text-[18px]">close</span>
                    </button>
                </div>
            @endif

            <!-- View Yield -->
            @yield('content')
        </main>

        <!-- Footer -->
        <footer class="mt-auto py-5 px-6 sm:px-8 border-t border-slate-200/80 bg-white text-xs text-slate-400 flex flex-col sm:flex-row items-center justify-between gap-2">
            <div>
                &copy; {{ date('Y') }} <strong class="text-slate-600 font-semibold">City Courier Indonesia</strong>. Seluruh hak cipta dilindungi.
            </div>
            <div class="flex items-center gap-4 text-slate-400">
                <span>Versi 2.4-stable</span>
                <span>&bull;</span>
                <span>Fonnte WhatsApp Integrated</span>
                <span>&bull;</span>
                <span>GoSend Instant Model</span>
            </div>
        </footer>

    </div>

    <!-- Core Scripts -->
    <script>
    function toggleSidebar() {
        const sidebar = document.getElementById('adminSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if (sidebar) sidebar.classList.toggle('-translate-x-full');
        if (overlay) overlay.classList.toggle('hidden');
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Date display
        const dateEl = document.getElementById('currentDateDisplay');
        if (dateEl) {
            const options = { weekday: 'long', year: 'numeric', month: 'short', day: 'numeric' };
            dateEl.textContent = new Date().toLocaleDateString('id-ID', options);
        }

        // Auto dismiss flash
        ['flashSuccess', 'flashError'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                setTimeout(() => {
                    el.style.opacity = '0';
                    setTimeout(() => el.remove(), 300);
                }, 4000);
            }
        });
    });
    </script>
    @stack('scripts')
</body>
</html>
