<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin Panel') - City Courier</title>
    <meta name="description" content="City Courier Admin Panel - Manage couriers, orders, and deliveries">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
    <div class="admin-layout">
        {{-- Sidebar --}}
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-shipping-fast"></i>
                </div>
                <div class="sidebar-brand">
                    <h1>City Courier</h1>
                    <span>Admin Panel</span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Menu Utama</div>
                    <a href="{{ route('admin.dashboard') }}"
                       class="nav-link {{ request()->routeIs('admin.dashboard*') ? 'active' : '' }}">
                        <i class="fas fa-chart-pie"></i>
                        <span>Dashboard</span>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Manajemen</div>
                    <a href="{{ route('admin.couriers', ['filter' => 'unverified']) }}"
                       class="nav-link {{ request('filter') === 'unverified' ? 'active' : '' }}">
                        <i class="fas fa-user-check"></i>
                        <span>Verifikasi Kurir</span>
                        @if(($unverified ?? 0) > 0)
                            <span class="nav-badge warning">{{ $unverified }}</span>
                        @endif
                    </a>
                    <a href="{{ route('admin.couriers', ['filter' => 'verified']) }}"
                       class="nav-link {{ request('filter') === 'verified' ? 'active' : '' }}">
                        <i class="fas fa-motorcycle"></i>
                        <span>Daftar Kurir</span>
                    </a>
                    <a href="{{ route('admin.orders') }}"
                       class="nav-link {{ request()->routeIs('admin.orders*') ? 'active' : '' }}">
                        <i class="fas fa-box"></i>
                        <span>Pesanan</span>
                        @if(($pendingOrders ?? 0) > 0)
                            <span class="nav-badge">{{ $pendingOrders }}</span>
                        @endif
                    </a>
                    <a href="{{ route('admin.drop-points.index') }}"
                       class="nav-link {{ request()->routeIs('admin.drop-points*') ? 'active' : '' }}">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Drop Point</span>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Sistem & Keamanan</div>
                    <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users*') ? 'active' : '' }}">
                        <i class="fas fa-users"></i>
                        <span>Manajemen User</span>
                    </a>
                    <a href="{{ route('admin.roles.index') }}" class="nav-link {{ request()->routeIs('admin.roles*') ? 'active' : '' }}">
                        <i class="fas fa-user-shield"></i>
                        <span>Manajemen Role</span>
                    </a>
                    <a href="{{ route('admin.permissions.index') }}" class="nav-link {{ request()->routeIs('admin.permissions*') ? 'active' : '' }}">
                        <i class="fas fa-key"></i>
                        <span>Manajemen Permission</span>
                    </a>
                    <a href="{{ route('admin.settings.whatsapp') }}" class="nav-link {{ request()->routeIs('admin.settings.whatsapp*') ? 'active' : '' }}">
                        <i class="fab fa-whatsapp"></i>
                        <span>Provider WhatsApp</span>
                    </a>
                </div>
            </nav>

            <div class="sidebar-footer">
                <div class="sidebar-user">
                    <div class="sidebar-user-avatar">
                        {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
                    </div>
                    <div class="sidebar-user-info">
                        <p>{{ auth()->user()->name ?? 'Admin' }}</p>
                        <span>Administrator</span>
                    </div>
                </div>
            </div>
        </aside>

        {{-- Sidebar Overlay --}}
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        {{-- Main Content --}}
        <div class="main-content">
            {{-- Navbar --}}
            <header class="navbar">
                <div class="navbar-left">
                    <button class="hamburger" id="hamburgerBtn" aria-label="Toggle sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div>
                        <div class="page-title">@yield('page-title', 'Dashboard')</div>
                        <div class="page-subtitle">@yield('page-subtitle', '')</div>
                    </div>
                </div>
                <div class="navbar-right">
                    <form action="{{ route('logout') }}" method="POST" style="margin:0;">
                        @csrf
                        <button type="submit" class="navbar-btn btn-logout">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </button>
                    </form>
                </div>
            </header>

            {{-- Content --}}
            <main class="content-area">
                {{-- Flash Messages --}}
                @if(session('success'))
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        {{ session('success') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        {{ session('error') }}
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <script src="{{ asset('js/admin.js') }}"></script>
    @stack('scripts')
</body>
</html>
