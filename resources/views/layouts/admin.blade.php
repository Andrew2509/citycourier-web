<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin Panel') - City Courier</title>
    <meta name="description" content="City Courier Admin Panel">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    'primary': '#059669',
                    'primary-light': '#10B981',
                    'primary-dark': '#047857',
                    'primary-50': '#ECFDF5',
                    'primary-100': '#D1FAE5',
                    'primary-200': '#A7F3D0',
                    'primary-500': '#10B981',
                    'primary-600': '#059669',
                    'primary-700': '#047857',
                    'primary-800': '#065F46',
                    'primary-900': '#064E3B',
                    'slate-50': '#F8FAFC',
                    'slate-100': '#F1F5F9',
                    'slate-200': '#E2E8F0',
                    'slate-300': '#CBD5E1',
                    'slate-400': '#94A3B8',
                    'slate-500': '#64748B',
                    'slate-600': '#475569',
                    'slate-700': '#334155',
                    'slate-800': '#1E293B',
                    'slate-900': '#0F172A',
                    'surface': '#F0FDF4',
                    'surface-card': '#FFFFFF',
                    'surface-border': '#D1FAE5',
                    'success': '#059669',
                    'warning': '#F59E0B',
                    'error': '#DC2626',
                    'info': '#0284C7',
                },
                fontFamily: {
                    'inter': ['Inter', 'sans-serif'],
                },
            },
        },
    }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style>
        * { font-family: 'Inter', sans-serif; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #A7F3D0; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #6EE7B7; }
        .sidebar-link.active { background: linear-gradient(135deg, #059669, #10B981); color: white; font-weight: 600; box-shadow: 0 4px 12px rgba(5,150,105,0.3); }
.stat-card { transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(5,150,105,0.15); }
    </style>
    @stack('styles')
</head>
<body class="bg-surface text-slate-800 h-screen overflow-hidden">
    <!-- Mobile Overlay -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="fixed left-0 top-0 h-full w-64 bg-white border-r border-primary-100 z-50 flex flex-col transform -translate-x-full lg:translate-x-0 transition-transform duration-300">
        <!-- Logo -->
        <div class="px-5 py-4 border-b border-primary-100 flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-primary to-primary-light flex items-center justify-center shadow-md shadow-primary/20">
                <span class="material-symbols-outlined text-white text-xl">local_shipping</span>
            </div>
            <div>
                <h1 class="text-base font-bold text-slate-800 leading-tight">CityCourier</h1>
                <span class="text-[10px] text-primary-600 uppercase tracking-widest font-semibold">Admin Panel</span>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto py-3 px-3">
            <!-- Menu Utama -->
            <div class="text-[10px] font-semibold text-primary-400 uppercase tracking-wider px-3 mb-2">Menu Utama</div>
            <a href="{{ route('admin.dashboard') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 transition-all {{ request()->routeIs('admin.dashboard*') ? 'active' : 'text-slate-600 hover:bg-primary-50 hover:text-primary-700' }}">
                <span class="material-symbols-outlined text-[20px]">dashboard</span>
                <span class="text-sm">Dashboard</span>
            </a>

            <!-- Manajemen -->
            <div class="text-[10px] font-semibold text-primary-400 uppercase tracking-wider px-3 mb-2 mt-4">Manajemen</div>
            <a href="{{ route('admin.couriers', ['filter' => 'unverified']) }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 transition-all {{ request('filter') === 'unverified' ? 'active' : 'text-slate-600 hover:bg-primary-50 hover:text-primary-700' }}">
                <span class="material-symbols-outlined text-[20px]">how_to_reg</span>
                <span class="text-sm">Verifikasi Kurir</span>
                @if(($unverified ?? 0) > 0)
                    <span class="ml-auto bg-warning/10 text-warning text-[10px] font-bold px-2 py-0.5 rounded-full">{{ $unverified }}</span>
                @endif
            </a>
            <a href="{{ route('admin.couriers', ['filter' => 'verified']) }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 transition-all {{ request('filter') === 'verified' ? 'active' : 'text-slate-600 hover:bg-primary-50 hover:text-primary-700' }}">
                <span class="material-symbols-outlined text-[20px]">group</span>
                <span class="text-sm">Daftar Kurir</span>
            </a>
            <a href="{{ route('admin.orders') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 transition-all {{ request()->routeIs('admin.orders*') ? 'active' : 'text-slate-600 hover:bg-primary-50 hover:text-primary-700' }}">
                <span class="material-symbols-outlined text-[20px]">shopping_bag</span>
                <span class="text-sm">Pesanan</span>
                @if(($pendingOrders ?? 0) > 0)
                    <span class="ml-auto bg-warning/10 text-warning text-[10px] font-bold px-2 py-0.5 rounded-full">{{ $pendingOrders }}</span>
                @endif
            </a>
            <a href="{{ route('admin.shipments.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 transition-all {{ request()->routeIs('admin.shipments*') ? 'active' : 'text-slate-600 hover:bg-primary-50 hover:text-primary-700' }}">
                <span class="material-symbols-outlined text-[20px]">local_shipping</span>
                <span class="text-sm">Pengiriman</span>
                @if(($pendingShipments ?? 0) > 0)
                    <span class="ml-auto bg-warning/10 text-warning text-[10px] font-bold px-2 py-0.5 rounded-full">{{ $pendingShipments }}</span>
                @endif
            </a>
            <a href="{{ route('admin.drop-points.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 transition-all {{ request()->routeIs('admin.drop-points*') ? 'active' : 'text-slate-600 hover:bg-primary-50 hover:text-primary-700' }}">
                <span class="material-symbols-outlined text-[20px]">pin_drop</span>
                <span class="text-sm">Drop Point</span>
            </a>

            <!-- City-Work -->
            <div class="text-[10px] font-semibold text-primary-400 uppercase tracking-wider px-3 mb-2 mt-4">City-Work Operasional</div>
            <a href="{{ route('admin.ci-work.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 transition-all {{ request()->routeIs('admin.ci-work.index*') ? 'active' : 'text-slate-600 hover:bg-primary-50 hover:text-primary-700' }}">
                <span class="material-symbols-outlined text-[20px]">work</span>
                <span class="text-sm">Dashboard Kerja</span>
            </a>
            <a href="{{ route('admin.ci-work.attendance') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 transition-all {{ request()->routeIs('admin.ci-work.attendance*') ? 'active' : 'text-slate-600 hover:bg-primary-50 hover:text-primary-700' }}">
                <span class="material-symbols-outlined text-[20px]">fingerprint</span>
                <span class="text-sm">Presensi Kurir</span>
            </a>
            <a href="{{ route('admin.ci-work.tasks') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 transition-all {{ request()->routeIs('admin.ci-work.tasks*') ? 'active' : 'text-slate-600 hover:bg-primary-50 hover:text-primary-700' }}">
                <span class="material-symbols-outlined text-[20px]">task</span>
                <span class="text-sm">Manajemen Tugas</span>
            </a>
            <a href="{{ route('admin.ci-work.finance') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 transition-all {{ request()->routeIs('admin.ci-work.finance*') ? 'active' : 'text-slate-600 hover:bg-primary-50 hover:text-primary-700' }}">
                <span class="material-symbols-outlined text-[20px]">payments</span>
                <span class="text-sm">Keuangan & Setoran</span>
            </a>

            <!-- Sistem -->
            <div class="text-[10px] font-semibold text-primary-400 uppercase tracking-wider px-3 mb-2 mt-4">Sistem & Keamanan</div>
            <a href="{{ route('admin.users.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 transition-all {{ request()->routeIs('admin.users*') ? 'active' : 'text-slate-600 hover:bg-primary-50 hover:text-primary-700' }}">
                <span class="material-symbols-outlined text-[20px]">manage_accounts</span>
                <span class="text-sm">Manajemen User</span>
            </a>
            <a href="{{ route('admin.roles.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 transition-all {{ request()->routeIs('admin.roles*') ? 'active' : 'text-slate-600 hover:bg-primary-50 hover:text-primary-700' }}">
                <span class="material-symbols-outlined text-[20px]">admin_panel_settings</span>
                <span class="text-sm">Manajemen Role</span>
            </a>
            <a href="{{ route('admin.permissions.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 transition-all {{ request()->routeIs('admin.permissions*') ? 'active' : 'text-slate-600 hover:bg-primary-50 hover:text-primary-700' }}">
                <span class="material-symbols-outlined text-[20px]">vpn_key</span>
                <span class="text-sm">Manajemen Permission</span>
            </a>
            <a href="{{ route('admin.settings.providers') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 transition-all {{ request()->routeIs('admin.settings.*') ? 'active' : 'text-slate-600 hover:bg-primary-50 hover:text-primary-700' }}">
                <span class="material-symbols-outlined text-[20px]">extension</span>
                <span class="text-sm">Provider & Integrasi</span>
            </a>
            <a href="{{ route('api.documentation') }}" target="_blank" rel="noopener" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 transition-all text-slate-600 hover:bg-primary-50 hover:text-primary-700">
                <span class="material-symbols-outlined text-[20px]">api</span>
                <span class="text-sm">Dokumentasi API</span>
                <span class="material-symbols-outlined text-[14px] ml-auto text-slate-400">open_in_new</span>
            </a>

            <!-- Download -->
            @php
                $activeApk = \App\Models\AppDownload::getActive();
            @endphp
            <div class="text-[10px] font-semibold text-primary-400 uppercase tracking-wider px-3 mb-2 mt-4">Unduh Aplikasi</div>
            <a href="{{ route('admin.app-download') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 transition-all {{ request()->routeIs('admin.app-download*') ? 'active' : 'text-slate-600 hover:bg-primary-50 hover:text-primary-700' }}">
                <span class="material-symbols-outlined text-[20px]">android</span>
                <span class="text-sm">Kelola APK</span>
            </a>
            <a href="{{ route('download.app') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 bg-primary-50 text-primary-700 hover:bg-primary-100 transition-all font-medium">
                <span class="material-symbols-outlined text-[20px]">download</span>
                <span class="text-sm">Download App v{{ $activeApk->version ?? '1.0.0' }}</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <div class="lg:ml-64 h-full flex flex-col">
        <!-- Header -->
        <header class="h-16 flex-shrink-0 bg-white/80 backdrop-blur-xl border-b border-primary-100 z-40 flex items-center justify-between px-4 lg:px-6">
            <div class="flex items-center gap-4 flex-1 lg:w-96">
                <button id="hamburgerBtn" class="lg:hidden p-2 text-slate-500 hover:text-primary-600 hover:bg-primary-50 rounded-xl transition-all">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <div class="relative w-full hidden sm:block">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 material-symbols-outlined text-primary-300 text-[18px]">search</span>
                    <input class="w-full bg-primary-50/50 border border-primary-100 text-slate-700 pl-10 pr-4 py-2 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="Cari pesanan, kurir, atau resi..." type="text"/>
                </div>
            </div>
            <div class="flex items-center gap-2 lg:gap-4">
                <a href="{{ route('download.app') }}" class="hidden sm:flex items-center gap-2 px-3 py-2 bg-primary-50 text-primary-700 rounded-xl hover:bg-primary-100 transition-all text-sm font-medium">
                    <span class="material-symbols-outlined text-[18px]">download</span>
                    <span class="hidden md:inline">Download App</span>
                </a>
                <button class="relative p-2 text-slate-400 hover:text-primary-600 hover:bg-primary-50 rounded-xl transition-all">
                    <span class="material-symbols-outlined">notifications</span>
                    <span class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-primary"></span>
                </button>
                <div class="h-8 w-[1px] bg-primary-100"></div>
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-primary to-primary-light flex items-center justify-center text-white font-bold text-sm shadow-md shadow-primary/20">
                        {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
                    </div>
                    <div class="hidden md:block">
                        <p class="text-sm font-semibold text-slate-700 leading-tight">{{ auth()->user()->name ?? 'Admin' }}</p>
                        <span class="text-[11px] text-primary-500">Administrator</span>
                    </div>
                </div>
                <form action="{{ route('logout') }}" method="POST" style="margin:0;">
                    @csrf
                    <button type="submit" class="flex items-center gap-2 px-3 py-2 rounded-xl text-sm text-slate-400 hover:text-error hover:bg-error/5 transition-all">
                        <span class="material-symbols-outlined text-[18px]">logout</span>
                    </button>
                </form>
            </div>
        </header>

        <!-- Content -->
        <main class="bg-surface flex-1 overflow-y-auto p-4 lg:p-6 pb-20">
            <!-- Mobile Download Banner -->
            <a href="{{ route('download.app') }}" class="sm:hidden flex items-center gap-3 mb-4 p-4 bg-gradient-to-r from-primary to-primary-light rounded-2xl text-white shadow-lg shadow-primary/30">
                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                    <span class="material-symbols-outlined text-2xl">android</span>
                </div>
                <div class="flex-1">
                    <p class="font-semibold">Download CityCourier</p>
                    <p class="text-xs text-white/80">Pasang aplikasi di perangkat Anda</p>
                </div>
                <span class="material-symbols-outlined">download</span>
            </a>
            <!-- Flash Messages -->
            @if(session('success'))
                <div id="flashSuccess" class="mb-4 p-4 rounded-xl bg-primary-50 border border-primary-200 text-primary-700 flex items-center gap-3">
                    <span class="material-symbols-outlined">check_circle</span>
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div id="flashError" class="mb-4 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 flex items-center gap-3">
                    <span class="material-symbols-outlined">error</span>
                    {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('hidden');
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
