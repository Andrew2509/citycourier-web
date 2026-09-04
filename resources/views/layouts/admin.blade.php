<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin Panel') - City Courier</title>
    <meta name="description" content="City Courier Admin Panel - Manage couriers, orders, and deliveries">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                "colors": {
                    "on-error": "#ffffff",
                    "on-background": "#191c1e",
                    "surface-variant": "#e0e3e5",
                    "surface-bright": "#f7f9fb",
                    "secondary-fixed-dim": "#c0c6db",
                    "surface": "#f7f9fb",
                    "primary-fixed-dim": "#ffb59a",
                    "primary-fixed": "#ffdbcf",
                    "on-primary-fixed-variant": "#802900",
                    "inverse-on-surface": "#eff1f3",
                    "surface-tint": "#a83900",
                    "on-surface": "#191c1e",
                    "on-tertiary-fixed-variant": "#38485d",
                    "background": "#f7f9fb",
                    "on-primary-fixed": "#380d00",
                    "inverse-primary": "#ffb59a",
                    "surface-container": "#eceef0",
                    "on-tertiary-fixed": "#0b1c30",
                    "tertiary-fixed-dim": "#b7c8e1",
                    "on-error-container": "#93000a",
                    "secondary-fixed": "#dce2f7",
                    "error": "#ba1a1a",
                    "surface-container-high": "#e6e8ea",
                    "surface-dim": "#d8dadc",
                    "surface-container-low": "#f2f4f6",
                    "surface-container-lowest": "#ffffff",
                    "on-secondary-fixed": "#141b2b",
                    "secondary": "#575e70",
                    "tertiary": "#505f76",
                    "primary-container": "#ff5a00",
                    "on-tertiary-container": "#1c2c40",
                    "on-secondary": "#ffffff",
                    "on-primary": "#ffffff",
                    "on-surface-variant": "#5b4137",
                    "surface-container-highest": "#e0e3e5",
                    "tertiary-container": "#8393ab",
                    "inverse-surface": "#2d3133",
                    "secondary-container": "#d9dff5",
                    "on-secondary-fixed-variant": "#404758",
                    "primary": "#a83900",
                    "tertiary-fixed": "#d3e4fe",
                    "on-tertiary": "#ffffff",
                    "outline": "#907065",
                    "on-secondary-container": "#5c6274",
                    "outline-variant": "#e4beb1",
                    "error-container": "#ffdad6",
                    "on-primary-container": "#511700"
                },
                "borderRadius": {
                    "DEFAULT": "0.25rem",
                    "lg": "0.5rem",
                    "xl": "0.75rem",
                    "full": "9999px"
                },
                "spacing": {
                    "gutter": "16px",
                    "section-gap": "32px",
                    "base": "4px",
                    "stack-sm": "8px",
                    "container-margin": "20px",
                    "stack-lg": "24px",
                    "stack-md": "16px",
                    "header-height": "64px",
                    "gutter-sm": "0.5rem",
                    "sidebar-width": "260px",
                    "gutter-xl": "2rem",
                    "gutter-md": "1rem",
                    "gutter-lg": "1.5rem",
                    "gutter-xs": "0.25rem"
                },
                "fontFamily": {
                    "display-lg": ["Inter"],
                    "label-sm": ["Inter"],
                    "body-sm": ["Inter"],
                    "label-md": ["Inter"],
                    "display-md": ["Inter"],
                    "body-lg": ["Inter"],
                    "headline-sm": ["Inter"],
                    "headline-lg": ["Inter"],
                    "label-lg": ["Inter"],
                    "body-md": ["Inter"],
                    "headline-md": ["Inter"]
                },
                "fontSize": {
                    "display-lg": ["28px", {"lineHeight": "34px", "letterSpacing": "-0.02em", "fontWeight": "700"}],
                    "label-sm": ["12px", {"lineHeight": "16px", "fontWeight": "400"}],
                    "body-sm": ["14px", {"lineHeight": "20px", "fontWeight": "400"}],
                    "label-md": ["13px", {"lineHeight": "18px", "fontWeight": "500"}],
                    "display-md": ["24px", {"lineHeight": "30px", "letterSpacing": "-0.01em", "fontWeight": "700"}],
                    "body-lg": ["16px", {"lineHeight": "24px", "fontWeight": "400"}],
                    "headline-sm": ["20px", {"lineHeight": "28px", "fontWeight": "600"}],
                    "headline-lg": ["28px", {"lineHeight": "36px", "letterSpacing": "-0.02em", "fontWeight": "600"}],
                    "label-lg": ["13px", {"lineHeight": "18px", "fontWeight": "500"}],
                    "headline-md": ["20px", {"lineHeight": "28px", "letterSpacing": "-0.01em", "fontWeight": "600"}],
                    "body-md": ["14px", {"lineHeight": "20px", "fontWeight": "400"}]
                }
            },
        },
    }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=inter:wght@100..900&display=swap" rel="stylesheet"/>
</head>
<body class="bg-surface font-body-md text-on-surface">
    <!-- Sidebar -->
    <aside class="fixed left-0 top-0 h-full w-sidebar-width bg-surface-container-lowest border-r border-surface-variant z-50 flex flex-col pt-gutter-md pb-gutter-xl overflow-y-auto">
        <div class="px-gutter-lg mb-gutter-md flex items-center gap-gutter-sm">
            <div class="w-8 h-8 rounded-full bg-primary-container flex items-center justify-center">
                <span class="material-symbols-outlined text-on-primary-container text-[18px]">local_shipping</span>
            </div>
            <span class="font-headline-md text-primary-container">CityCourier</span>
        </div>
        <nav class="flex-1 px-gutter-sm space-y-gutter-xs">
            <div class="px-gutter-sm pt-gutter-md pb-gutter-xs text-label-sm text-outline uppercase tracking-wider">Menu Utama</div>
            <a href="{{ route('admin.dashboard') }}" class="flex items-center px-gutter-md py-gutter-sm rounded-xl transition-all {{ request()->routeIs('admin.dashboard*') ? 'bg-primary-container text-on-primary-container font-bold' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}">
                <span class="material-symbols-outlined mr-gutter-md text-[20px]">dashboard</span>Dashboard
            </a>

            <div class="px-gutter-sm pt-gutter-md pb-gutter-xs text-label-sm text-outline uppercase tracking-wider">Manajemen</div>
            <a href="{{ route('admin.couriers', ['filter' => 'unverified']) }}" class="flex items-center px-gutter-md py-gutter-sm rounded-xl transition-all {{ request('filter') === 'unverified' ? 'bg-primary-container text-on-primary-container font-bold' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}">
                <span class="material-symbols-outlined mr-gutter-md text-[20px]">verified_user</span>Verifikasi Kurir
                @if(($unverified ?? 0) > 0)
                    <span class="ml-auto px-2 py-0.5 rounded-full text-xs bg-amber-500/10 text-amber-600 font-medium">{{ $unverified }}</span>
                @endif
            </a>
            <a href="{{ route('admin.couriers', ['filter' => 'verified']) }}" class="flex items-center px-gutter-md py-gutter-sm rounded-xl transition-all {{ request('filter') === 'verified' ? 'bg-primary-container text-on-primary-container font-bold' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}">
                <span class="material-symbols-outlined mr-gutter-md text-[20px]">group</span>Daftar Kurir
            </a>
            <a href="{{ route('admin.orders') }}" class="flex items-center px-gutter-md py-gutter-sm rounded-xl transition-all {{ request()->routeIs('admin.orders*') ? 'bg-primary-container text-on-primary-container font-bold' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}">
                <span class="material-symbols-outlined mr-gutter-md text-[20px]">orders</span>Pesanan
                @if(($pendingOrders ?? 0) > 0)
                    <span class="ml-auto px-2 py-0.5 rounded-full text-xs bg-amber-500/10 text-amber-600 font-medium">{{ $pendingOrders }}</span>
                @endif
            </a>
            <a href="{{ route('admin.shipments.index') }}" class="flex items-center px-gutter-md py-gutter-sm rounded-xl transition-all {{ request()->routeIs('admin.shipments*') ? 'bg-primary-container text-on-primary-container font-bold' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}">
                <span class="material-symbols-outlined mr-gutter-md text-[20px]">local_shipping</span>Pengiriman
                @if(($pendingShipments ?? 0) > 0)
                    <span class="ml-auto px-2 py-0.5 rounded-full text-xs bg-amber-500/10 text-amber-600 font-medium">{{ $pendingShipments }}</span>
                @endif
            </a>
            <a href="{{ route('admin.drop-points.index') }}" class="flex items-center px-gutter-md py-gutter-sm rounded-xl transition-all {{ request()->routeIs('admin.drop-points*') ? 'bg-primary-container text-on-primary-container font-bold' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}">
                <span class="material-symbols-outlined mr-gutter-md text-[20px]">pin_drop</span>Drop Point
            </a>

            <div class="px-gutter-sm pt-gutter-md pb-gutter-xs text-label-sm text-outline uppercase tracking-wider">City-Work Operasional</div>
            <a href="{{ route('admin.ci-work.index') }}" class="flex items-center px-gutter-md py-gutter-sm rounded-xl transition-all {{ request()->routeIs('admin.ci-work.index*') ? 'bg-primary-container text-on-primary-container font-bold' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}">
                <span class="material-symbols-outlined mr-gutter-md text-[20px]">work</span>Dashboard Kerja
            </a>
            <a href="{{ route('admin.ci-work.attendance') }}" class="flex items-center px-gutter-md py-gutter-sm rounded-xl transition-all {{ request()->routeIs('admin.ci-work.attendance*') ? 'bg-primary-container text-on-primary-container font-bold' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}">
                <span class="material-symbols-outlined mr-gutter-md text-[20px]">fingerprint</span>Presensi Kurir
            </a>
            <a href="{{ route('admin.ci-work.tasks') }}" class="flex items-center px-gutter-md py-gutter-sm rounded-xl transition-all {{ request()->routeIs('admin.ci-work.tasks*') ? 'bg-primary-container text-on-primary-container font-bold' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}">
                <span class="material-symbols-outlined mr-gutter-md text-[20px]">task</span>Manajemen Tugas
            </a>
            <a href="{{ route('admin.ci-work.finance') }}" class="flex items-center px-gutter-md py-gutter-sm rounded-xl transition-all {{ request()->routeIs('admin.ci-work.finance*') ? 'bg-primary-container text-on-primary-container font-bold' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}">
                <span class="material-symbols-outlined mr-gutter-md text-[20px]">payments</span>Keuangan & Setoran
            </a>

            <div class="px-gutter-sm pt-gutter-md pb-gutter-xs text-label-sm text-outline uppercase tracking-wider">Sistem & Keamanan</div>
            <a href="{{ route('admin.users.index') }}" class="flex items-center px-gutter-md py-gutter-sm rounded-xl transition-all {{ request()->routeIs('admin.users*') ? 'bg-primary-container text-on-primary-container font-bold' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}">
                <span class="material-symbols-outlined mr-gutter-md text-[20px]">manage_accounts</span>Manajemen User
            </a>
            <a href="{{ route('admin.roles.index') }}" class="flex items-center px-gutter-md py-gutter-sm rounded-xl transition-all {{ request()->routeIs('admin.roles*') ? 'bg-primary-container text-on-primary-container font-bold' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}">
                <span class="material-symbols-outlined mr-gutter-md text-[20px]">admin_panel_settings</span>Manajemen Role
            </a>
            <a href="{{ route('admin.permissions.index') }}" class="flex items-center px-gutter-md py-gutter-sm rounded-xl transition-all {{ request()->routeIs('admin.permissions*') ? 'bg-primary-container text-on-primary-container font-bold' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}">
                <span class="material-symbols-outlined mr-gutter-md text-[20px]">vpn_key</span>Manajemen Permission
            </a>
            <a href="{{ route('admin.settings.whatsapp') }}" class="flex items-center px-gutter-md py-gutter-sm rounded-xl transition-all {{ request()->routeIs('admin.settings.whatsapp*') ? 'bg-primary-container text-on-primary-container font-bold' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}">
                <span class="material-symbols-outlined mr-gutter-md text-[20px]">chat</span>Provider WhatsApp
            </a>
            <a href="{{ route('admin.settings.rajaongkir') }}" class="flex items-center px-gutter-md py-gutter-sm rounded-xl transition-all {{ request()->routeIs('admin.settings.rajaongkir*') ? 'bg-primary-container text-on-primary-container font-bold' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}">
                <span class="material-symbols-outlined mr-gutter-md text-[20px]">api</span>Provider RajaOngkir
            </a>
            <a href="{{ route('admin.settings.payment') }}" class="flex items-center px-gutter-md py-gutter-sm rounded-xl transition-all {{ request()->routeIs('admin.settings.payment*') ? 'bg-primary-container text-on-primary-container font-bold' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}">
                <span class="material-symbols-outlined mr-gutter-md text-[20px]">credit_card</span>Layanan Pembayaran
            </a>
            <a href="{{ route('admin.settings.map') }}" class="flex items-center px-gutter-md py-gutter-sm rounded-xl transition-all {{ request()->routeIs('admin.settings.map*') ? 'bg-primary-container text-on-primary-container font-bold' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}">
                <span class="material-symbols-outlined mr-gutter-md text-[20px]">map</span>Konfigurasi Peta
            </a>
            <a href="{{ route('admin.settings.dana') }}" class="flex items-center px-gutter-md py-gutter-sm rounded-xl transition-all {{ request()->routeIs('admin.settings.dana*') ? 'bg-primary-container text-on-primary-container font-bold' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}">
                <span class="material-symbols-outlined mr-gutter-md text-[20px]">account_balance_wallet</span>Provider DANA
            </a>

            <div class="px-gutter-sm pt-gutter-md pb-gutter-xs text-label-sm text-outline uppercase tracking-wider">Unduh Aplikasi</div>
            <a href="{{ asset('downloads/citycourier.apk') }}" class="flex items-center px-gutter-md py-gutter-sm rounded-xl text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-all" download>
                <span class="material-symbols-outlined mr-gutter-md text-[20px]">qr_code_scanner</span>Aplikasi v1.0.0
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <div class="pl-sidebar-width">
        <!-- Header -->
        <header class="fixed top-0 left-sidebar-width right-0 h-header-height bg-surface/80 backdrop-blur-xl border-b border-surface-variant z-40 flex items-center justify-between px-gutter-xl">
            <div class="flex items-center gap-gutter-md w-96">
                <div class="relative w-full">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-gutter-md material-symbols-outlined text-outline text-[18px]">search</span>
                    <input class="w-full bg-surface-container-low border border-surface-variant text-on-surface pl-10 pr-gutter-md py-2 rounded-xl text-body-sm focus:outline-none focus:ring-1 focus:ring-primary-container" placeholder="Global search..." type="text"/>
                </div>
            </div>
            <div class="flex items-center gap-gutter-lg">
                <button class="relative text-on-surface-variant hover:text-on-surface">
                    <span class="material-symbols-outlined">notifications</span>
                    <span class="absolute top-0 right-0 w-2 h-2 rounded-full bg-primary-container"></span>
                </button>
                <div class="flex items-center gap-gutter-sm">
                    <div class="w-8 h-8 rounded-full bg-primary-container flex items-center justify-center">
                        <span class="material-symbols-outlined text-on-primary-container text-[18px]">person</span>
                    </div>
                    <span class="text-label-lg">{{ auth()->user()->name ?? 'Admin CityCourier' }}</span>
                </div>
                <form action="{{ route('logout') }}" method="POST" style="margin:0;">
                    @csrf
                    <button type="submit" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm text-red-500 hover:bg-red-50 transition-all">
                        <span class="material-symbols-outlined text-[18px]">logout</span>
                        <span>Logout</span>
                    </button>
                </form>
            </div>
        </header>

        <!-- Content -->
        <main class="relative pt-header-height bg-surface min-h-screen p-gutter-xl">
            <!-- Flash Messages -->
            @if(session('success'))
                <div class="mb-4 p-4 rounded-xl bg-green-500/10 border border-green-500/20 text-green-600 flex items-center gap-3">
                    <span class="material-symbols-outlined">check_circle</span>
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-500 flex items-center gap-3">
                    <span class="material-symbols-outlined">error</span>
                    {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <script>
    // Sidebar toggle for mobile
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.querySelector('aside');
        const overlay = document.getElementById('sidebarOverlay');
        const hamburger = document.getElementById('hamburgerBtn');

        if (hamburger) {
            hamburger.addEventListener('click', function() {
                sidebar.classList.toggle('hidden');
                overlay.classList.toggle('hidden');
            });
        }

        if (overlay) {
            overlay.addEventListener('click', function() {
                sidebar.classList.add('hidden');
                overlay.classList.add('hidden');
            });
        }
    });
    </script>
    @stack('scripts')
</body>
</html>
