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
                    // Primary - sesuai Flutter app
                    'primary': '#EC5B13',
                    'primary-light': '#FF8A00',
                    'primary-dark': '#D4510F',
                    'primary-50': '#FFF3E8',
                    'primary-100': '#FFE1CC',
                    'primary-500': '#EC5B13',
                    'primary-600': '#D4510F',
                    'primary-700': '#B8450D',

                    // Slate - sesuai Flutter
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

                    // Surface
                    'surface': '#F9FAFB',
                    'surface-card': '#FFFFFF',
                    'surface-border': '#E8E8E8',

                    // Status
                    'success': '#4CAF50',
                    'warning': '#FF9800',
                    'error': '#F44336',
                    'info': '#2196F3',
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
        ::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #94A3B8; }
    </style>
</head>
<body class="bg-surface text-slate-800">
    <!-- Sidebar -->
    <aside class="fixed left-0 top-0 h-full w-64 bg-white border-r border-surface-border z-50 flex flex-col">
        <!-- Logo -->
        <div class="px-5 py-4 border-b border-surface-border flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-primary to-primary-light flex items-center justify-center shadow-md shadow-primary/20">
                <span class="material-symbols-outlined text-white text-xl">local_shipping</span>
            </div>
            <div>
                <h1 class="text-base font-bold text-slate-800 leading-tight">CityCourier</h1>
                <span class="text-[10px] text-slate-400 uppercase tracking-widest">Admin Panel</span>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto py-3 px-3">
            <!-- Menu Utama -->
            <div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider px-3 mb-2">Menu Utama</div>
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 transition-all {{ request()->routeIs('admin.dashboard*') ? 'bg-primary text-white font-semibold shadow-md shadow-primary/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-800' }}">
                <span class="material-symbols-outlined text-[20px]">dashboard</span>
                <span class="text-sm">Dashboard</span>
            </a>

            <!-- Manajemen -->
            <div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider px-3 mb-2 mt-4">Manajemen</div>
            <a href="{{ route('admin.couriers', ['filter' => 'unverified']) }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 transition-all {{ request('filter') === 'unverified' ? 'bg-primary text-white font-semibold shadow-md shadow-primary/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-800' }}">
                <span class="material-symbols-outlined text-[20px]">verified_user</span>
                <span class="text-sm">Verifikasi Kurir</span>
                @if(($unverified ?? 0) > 0)
                    <span class="ml-auto bg-warning/10 text-warning text-[10px] font-bold px-2 py-0.5 rounded-full">{{ $unverified }}</span>
                @endif
            </a>
            <a href="{{ route('admin.couriers', ['filter' => 'verified']) }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 transition-all {{ request('filter') === 'verified' ? 'bg-primary text-white font-semibold shadow-md shadow-primary/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-800' }}">
                <span class="material-symbols-outlined text-[20px]">group</span>
                <span class="text-sm">Daftar Kurir</span>
            </a>
            <a href="{{ route('admin.orders') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 transition-all {{ request()->routeIs('admin.orders*') ? 'bg-primary text-white font-semibold shadow-md shadow-primary/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-800' }}">
                <span class="material-symbols-outlined text-[20px]">shopping_bag</span>
                <span class="text-sm">Pesanan</span>
                @if(($pendingOrders ?? 0) > 0)
                    <span class="ml-auto bg-warning/10 text-warning text-[10px] font-bold px-2 py-0.5 rounded-full">{{ $pendingOrders }}</span>
                @endif
            </a>
            <a href="{{ route('admin.shipments.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 transition-all {{ request()->routeIs('admin.shipments*') ? 'bg-primary text-white font-semibold shadow-md shadow-primary/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-800' }}">
                <span class="material-symbols-outlined text-[20px]">local_shipping</span>
                <span class="text-sm">Pengiriman</span>
                @if(($pendingShipments ?? 0) > 0)
                    <span class="ml-auto bg-warning/10 text-warning text-[10px] font-bold px-2 py-0.5 rounded-full">{{ $pendingShipments }}</span>
                @endif
            </a>
            <a href="{{ route('admin.drop-points.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 transition-all {{ request()->routeIs('admin.drop-points*') ? 'bg-primary text-white font-semibold shadow-md shadow-primary/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-800' }}">
                <span class="material-symbols-outlined text-[20px]">pin_drop</span>
                <span class="text-sm">Drop Point</span>
            </a>

            <!-- City-Work -->
            <div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider px-3 mb-2 mt-4">City-Work Operasional</div>
            <a href="{{ route('admin.ci-work.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 transition-all {{ request()->routeIs('admin.ci-work.index*') ? 'bg-primary text-white font-semibold shadow-md shadow-primary/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-800' }}">
                <span class="material-symbols-outlined text-[20px]">work</span>
                <span class="text-sm">Dashboard Kerja</span>
            </a>
            <a href="{{ route('admin.ci-work.attendance') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 transition-all {{ request()->routeIs('admin.ci-work.attendance*') ? 'bg-primary text-white font-semibold shadow-md shadow-primary/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-800' }}">
                <span class="material-symbols-outlined text-[20px]">fingerprint</span>
                <span class="text-sm">Presensi Kurir</span>
            </a>
            <a href="{{ route('admin.ci-work.tasks') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 transition-all {{ request()->routeIs('admin.ci-work.tasks*') ? 'bg-primary text-white font-semibold shadow-md shadow-primary/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-800' }}">
                <span class="material-symbols-outlined text-[20px]">task</span>
                <span class="text-sm">Manajemen Tugas</span>
            </a>
            <a href="{{ route('admin.ci-work.finance') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 transition-all {{ request()->routeIs('admin.ci-work.finance*') ? 'bg-primary text-white font-semibold shadow-md shadow-primary/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-800' }}">
                <span class="material-symbols-outlined text-[20px]">payments</span>
                <span class="text-sm">Keuangan & Setoran</span>
            </a>

            <!-- Sistem -->
            <div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider px-3 mb-2 mt-4">Sistem & Keamanan</div>
            <a href="{{ route('admin.users.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 transition-all {{ request()->routeIs('admin.users*') ? 'bg-primary text-white font-semibold shadow-md shadow-primary/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-800' }}">
                <span class="material-symbols-outlined text-[20px]">manage_accounts</span>
                <span class="text-sm">Manajemen User</span>
            </a>
            <a href="{{ route('admin.roles.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 transition-all {{ request()->routeIs('admin.roles*') ? 'bg-primary text-white font-semibold shadow-md shadow-primary/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-800' }}">
                <span class="material-symbols-outlined text-[20px]">admin_panel_settings</span>
                <span class="text-sm">Manajemen Role</span>
            </a>
            <a href="{{ route('admin.permissions.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 transition-all {{ request()->routeIs('admin.permissions*') ? 'bg-primary text-white font-semibold shadow-md shadow-primary/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-800' }}">
                <span class="material-symbols-outlined text-[20px]">vpn_key</span>
                <span class="text-sm">Manajemen Permission</span>
            </a>
            <a href="{{ route('admin.settings.whatsapp') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 transition-all {{ request()->routeIs('admin.settings.whatsapp*') ? 'bg-primary text-white font-semibold shadow-md shadow-primary/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-800' }}">
                <span class="material-symbols-outlined text-[20px]">chat</span>
                <span class="text-sm">Provider WhatsApp</span>
            </a>
            <a href="{{ route('admin.settings.rajaongkir') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 transition-all {{ request()->routeIs('admin.settings.rajaongkir*') ? 'bg-primary text-white font-semibold shadow-md shadow-primary/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-800' }}">
                <span class="material-symbols-outlined text-[20px]">api</span>
                <span class="text-sm">Provider RajaOngkir</span>
            </a>
            <a href="{{ route('admin.settings.payment') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 transition-all {{ request()->routeIs('admin.settings.payment*') ? 'bg-primary text-white font-semibold shadow-md shadow-primary/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-800' }}">
                <span class="material-symbols-outlined text-[20px]">credit_card</span>
                <span class="text-sm">Layanan Pembayaran</span>
            </a>
            <a href="{{ route('admin.settings.map') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 transition-all {{ request()->routeIs('admin.settings.map*') ? 'bg-primary text-white font-semibold shadow-md shadow-primary/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-80' }}">
                <span class="material-symbols-outlined text-[20px]">map</span>
                <span class="text-sm">Konfigurasi Peta</span>
            </a>
            <a href="{{ route('admin.settings.dana') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 transition-all {{ request()->routeIs('admin.settings.dana*') ? 'bg-primary text-white font-semibold shadow-md shadow-primary/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-800' }}">
                <span class="material-symbols-outlined text-[20px]">account_balance_wallet</span>
                <span class="text-sm">Provider DANA</span>
            </a>

            <!-- Download -->
            <div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider px-3 mb-2 mt-4">Unduh Aplikasi</div>
            <a href="{{ asset('downloads/citycourier.apk') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5 text-slate-600 hover:bg-slate-50 hover:text-slate-800 transition-all" download>
                <span class="material-symbols-outlined text-[20px]">qr_code_scanner</span>
                <span class="text-sm">Aplikasi v1.0.0</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <div class="ml-64">
        <!-- Header -->
        <header class="fixed top-0 right-0 left-64 h-16 bg-white/80 backdrop-blur-xl border-b border-surface-border z-40 flex items-center justify-between px-6">
            <div class="flex items-center gap-4 w-96">
                <div class="relative w-full">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 material-symbols-outlined text-slate-400 text-[18px]">search</span>
                    <input class="w-full bg-slate-50 border border-slate-200 text-slate-700 pl-10 pr-4 py-2 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="Cari pesanan, kurir, atau resi..." type="text"/>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <button class="relative p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-50 rounded-xl transition-all">
                    <span class="material-symbols-outlined">notifications</span>
                    <span class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-primary"></span>
                </button>
                <div class="h-8 w-[1px] bg-slate-200"></div>
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-primary to-primary-light flex items-center justify-center text-white font-bold text-sm shadow-md shadow-primary/20">
                        {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-700 leading-tight">{{ auth()->user()->name ?? 'Admin' }}</p>
                        <span class="text-[11px] text-slate-400">Administrator</span>
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
        <main class="relative pt-16 bg-surface min-h-screen p-6">
            <!-- Flash Messages -->
            @if(session('success'))
                <div class="mb-4 p-4 rounded-xl bg-success/10 border border-success/20 text-success flex items-center gap-3">
                    <span class="material-symbols-outlined">check_circle</span>
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 p-4 rounded-xl bg-error/10 border border-error/20 text-error flex items-center gap-3">
                    <span class="material-symbols-outlined">error</span>
                    {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <script>
    // Mobile sidebar toggle
    document.addEventListener('DOMContentLoaded', function() {
        const hamburger = document.getElementById('hamburgerBtn');
        const sidebar = document.querySelector('aside');
        const overlay = document.getElementById('sidebarOverlay');

        if (hamburger) {
            hamburger.addEventListener('click', function() {
                sidebar.classList.toggle('-translate-x-full');
            });
        }
    });
    </script>
    @stack('scripts')
</body>
</html>
