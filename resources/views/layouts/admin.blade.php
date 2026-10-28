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
    <script src="https://cdn.tailwindcss.com"></script>
    <script id="tailwind-config">
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                colors: {
                    /* Identitas City Courier: navy + oranye */
                    brand: {
                        50: '#fff7ed', 100: '#ffedd5', 200: '#fed7aa', 300: '#fdba74', 400: '#fb923c',
                        500: '#f97316', 600: '#ec5b13', 700: '#c2410c', 800: '#9a3412', 900: '#7c2d12',
                    },
                    navy: { 800: '#1e293b', 900: '#0f172a', 950: '#0b132b' },

                    /* Token lama (dipakai di seluruh view) dipetakan ke palet yang sama */
                    "primary": "#c2410c",
                    "primary-container": "#ec5b13",
                    "on-primary": "#ffffff",
                    "on-primary-container": "#ffffff",
                    "primary-fixed": "#ffedd5",
                    "primary-fixed-dim": "#fed7aa",
                    "on-primary-fixed": "#7c2d12",
                    "on-primary-fixed-variant": "#9a3412",
                    "inverse-primary": "#fdba74",
                    "surface-tint": "#ec5b13",
                    "secondary": "#64748b",
                    "on-secondary": "#ffffff",
                    "secondary-container": "#f1f5f9",
                    "on-secondary-container": "#334155",
                    "secondary-fixed": "#e2e8f0",
                    "secondary-fixed-dim": "#cbd5e1",
                    "on-secondary-fixed": "#0f172a",
                    "on-secondary-fixed-variant": "#334155",
                    "tertiary": "#475569",
                    "background": "#f8fafc",
                    "on-background": "#0f172a",
                    "surface": "#f8fafc",
                    "surface-bright": "#ffffff",
                    "surface-dim": "#e2e8f0",
                    "surface-variant": "#f1f5f9",
                    "on-surface": "#0f172a",
                    "on-surface-variant": "#475569",
                    "surface-container-lowest": "#ffffff",
                    "surface-container-low": "#f8fafc",
                    "surface-container": "#f1f5f9",
                    "surface-container-high": "#e2e8f0",
                    "surface-container-highest": "#cbd5e1",
                    "inverse-surface": "#1e293b",
                    "inverse-on-surface": "#f1f5f9",
                    "outline": "#94a3b8",
                    "outline-variant": "#e2e8f0",
                    "error": "#dc2626",
                    "on-error": "#ffffff",
                    "error-container": "#fee2e2",
                    "on-error-container": "#991b1b"
                },
                borderRadius: {
                    "DEFAULT": "0.25rem",
                    "md": "0.375rem",
                    "lg": "0.375rem",
                    "xl": "0.5rem",
                    "2xl": "0.625rem",
                    "3xl": "0.75rem",
                    "full": "9999px"
                },
                boxShadow: {
                    "sm": "0 1px 2px 0 rgba(15, 23, 42, 0.04)",
                    "DEFAULT": "0 1px 3px 0 rgba(15, 23, 42, 0.06)",
                    "md": "0 2px 6px -1px rgba(15, 23, 42, 0.07)",
                    "lg": "0 8px 20px -4px rgba(15, 23, 42, 0.10)",
                    "xl": "0 12px 28px -6px rgba(15, 23, 42, 0.14)",
                    "2xl": "0 16px 36px -8px rgba(15, 23, 42, 0.18)"
                },
                spacing: {
                    "space-2xs": "0.125rem",
                    "space-xs": "0.25rem",
                    "space-sm": "0.5rem",
                    "space-md": "0.75rem",
                    "space-lg": "1rem",
                    "space-xl": "1.25rem",
                    "space-2xl": "1.75rem",
                    "space-base": "1rem",
                    "gutter-table": "0.75rem",
                    "sidebar-width": "15rem",
                    "sidebar-collapsed": "4.5rem",
                    "header-height": "3.5rem"
                },
                fontFamily: {
                    sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    display: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    mono: ['ui-monospace', 'SFMono-Regular', 'Consolas', 'Menlo', 'monospace'],
                    "display-lg": ['Inter', 'sans-serif'],
                    "headline-xl": ['Inter', 'sans-serif'],
                    "headline-lg": ['Inter', 'sans-serif'],
                    "headline-sm": ['Inter', 'sans-serif'],
                    "body-lg": ['Inter', 'sans-serif'],
                    "body-md": ['Inter', 'sans-serif'],
                    "body-sm": ['Inter', 'sans-serif'],
                    "label-md": ['Inter', 'sans-serif'],
                    "label-sm": ['Inter', 'sans-serif'],
                    "label-xs": ['Inter', 'sans-serif'],
                    "data-mono": ['ui-monospace', 'SFMono-Regular', 'Consolas', 'Menlo', 'monospace']
                },
                /* Skala tipografi proporsional untuk dashboard operasional */
                fontSize: {
                    "display-lg":  ["22px", { lineHeight: "30px", letterSpacing: "-0.01em", fontWeight: "600" }],
                    "headline-xl": ["19px", { lineHeight: "26px", letterSpacing: "-0.01em", fontWeight: "600" }],
                    "headline-lg": ["16px", { lineHeight: "24px", fontWeight: "600" }],
                    "headline-sm": ["14px", { lineHeight: "20px", fontWeight: "600" }],
                    "body-lg":     ["14px", { lineHeight: "22px" }],
                    "body-md":     ["13.5px", { lineHeight: "20px" }],
                    "body-sm":     ["13px", { lineHeight: "18px" }],
                    "label-md":    ["12.5px", { lineHeight: "16px", fontWeight: "500" }],
                    "label-sm":    ["11.5px", { lineHeight: "15px", fontWeight: "500" }],
                    "label-xs":    ["10.5px", { lineHeight: "14px", fontWeight: "500" }],
                    "data-mono":   ["12.5px", { lineHeight: "18px", fontWeight: "500" }]
                }
            }
        }
    };
    </script>

    {{-- Komponen UI bersama untuk seluruh halaman admin --}}
    <style type="text/tailwindcss">
        @layer base {
            html, body { margin: 0; padding: 0; }
            body { @apply bg-slate-50 text-slate-800 text-[13.5px] antialiased; overscroll-behavior: none; }
            .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 20; }
        }
        @layer components {
            /* Header halaman */
            .page-header   { @apply flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between; }
            .page-title    { @apply text-[19px] leading-7 font-semibold text-slate-900; }
            .page-subtitle { @apply text-[13px] text-slate-500 mt-0.5; }
            .page-actions  { @apply flex flex-wrap items-center gap-2; }
            .back-link     { @apply inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 hover:text-slate-800 transition-colors; }

            /* Card */
            .card          { @apply bg-white border border-slate-200 rounded-lg; }
            .card-header   { @apply flex items-center justify-between gap-3 px-4 py-3 border-b border-slate-200; }
            .card-title    { @apply text-sm font-semibold text-slate-900; }
            .card-subtitle { @apply text-xs text-slate-500 mt-0.5; }
            .card-body     { @apply p-4; }
            .card-footer   { @apply flex flex-col gap-2 px-4 py-3 border-t border-slate-200 sm:flex-row sm:items-center sm:justify-between text-xs text-slate-500; }

            /* Statistik ringkas */
            .stat-label    { @apply text-xs font-medium text-slate-500; }
            .stat-value    { @apply mt-1 text-xl font-semibold text-slate-900 tabular-nums; }
            .stat-meta     { @apply mt-1 text-xs text-slate-500; }

            /* Tombol */
            .btn           { @apply inline-flex items-center justify-center gap-1.5 h-9 px-3.5 rounded-md text-[13px] font-medium whitespace-nowrap transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40 disabled:opacity-50 disabled:cursor-not-allowed; }
            .btn .material-symbols-outlined { @apply text-[18px]; }
            .btn-sm        { @apply h-8 px-2.5 text-xs; }
            .btn-sm .material-symbols-outlined { @apply text-[16px]; }
            .btn-primary   { @apply bg-brand-600 text-white hover:bg-brand-700; }
            .btn-secondary { @apply bg-white text-slate-700 border border-slate-300 hover:bg-slate-50; }
            .btn-dark      { @apply bg-navy-900 text-white hover:bg-navy-800; }
            .btn-danger    { @apply bg-red-600 text-white hover:bg-red-700; }
            .btn-success   { @apply bg-emerald-600 text-white hover:bg-emerald-700; }
            .btn-ghost     { @apply text-slate-600 hover:bg-slate-100 hover:text-slate-900; }
            .btn-icon      { @apply inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition-colors; }
            .btn-icon .material-symbols-outlined { @apply text-[18px]; }
            .btn-icon-danger { @apply hover:bg-red-50 hover:text-red-600; }

            /* Form */
            .form-label    { @apply block mb-1.5 text-[13px] font-medium text-slate-700; }
            .form-input, .form-select, .form-textarea {
                @apply block w-full rounded-md border border-slate-300 bg-white px-3 text-[13.5px] text-slate-900 placeholder:text-slate-400 transition-colors focus:outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-500/20 disabled:bg-slate-50 disabled:text-slate-500;
            }
            .form-input, .form-select { @apply h-9; }
            .form-select   { @apply pr-8; }
            .form-textarea { @apply py-2 min-h-[80px]; }
            .form-help     { @apply mt-1 text-xs text-slate-500; }
            .form-error    { @apply mt-1 text-xs text-red-600; }
            .form-invalid  { @apply border-red-500 focus:border-red-500 focus:ring-red-500/20; }
            .form-check    { @apply h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500; }
            .form-section  { @apply text-xs font-semibold uppercase tracking-wide text-slate-500; }

            /* Pencarian & filter */
            .search-box    { @apply relative; }
            .search-box > .material-symbols-outlined { @apply pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-[18px] text-slate-400; }
            .search-box > input { @apply pl-9; }
            .filter-tabs   { @apply flex items-center gap-1 overflow-x-auto; }
            .filter-tab    { @apply inline-flex shrink-0 items-center gap-1.5 h-8 px-3 rounded-md text-[13px] font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors; }
            .filter-tab.is-active { @apply bg-navy-900 text-white hover:bg-navy-900 hover:text-white; }
            .filter-count  { @apply rounded px-1.5 text-[11px] font-semibold tabular-nums bg-slate-200/80 text-slate-600; }
            .filter-tab.is-active .filter-count { @apply bg-white/15 text-white; }

            /* Tabel data */
            .table-wrap    { @apply w-full overflow-x-auto; }
            .data-table    { @apply w-full text-left border-collapse; }
            .data-table thead th { @apply px-4 py-2.5 bg-slate-50 border-b border-slate-200 text-xs font-medium text-slate-500 whitespace-nowrap; }
            .data-table tbody td { @apply px-4 py-3 border-b border-slate-100 text-[13px] text-slate-700 align-middle; }
            .data-table tbody tr:last-child td { @apply border-b-0; }
            .data-table tbody tr:hover td { @apply bg-slate-50/70; }
            .cell-primary  { @apply font-medium text-slate-900; }
            .cell-muted    { @apply text-xs text-slate-500; }
            .cell-mono     { @apply font-mono text-[12.5px]; }
            .link          { @apply text-brand-700 hover:text-brand-800 hover:underline; }

            /* Badge status */
            .badge         { @apply inline-flex items-center gap-1 rounded px-2 py-0.5 text-xs font-medium whitespace-nowrap; }
            .badge-dot     { @apply h-1.5 w-1.5 rounded-full bg-current; }
            .badge-gray    { @apply bg-slate-100 text-slate-700; }
            .badge-blue    { @apply bg-blue-50 text-blue-700; }
            .badge-indigo  { @apply bg-indigo-50 text-indigo-700; }
            .badge-amber   { @apply bg-amber-50 text-amber-700; }
            .badge-orange  { @apply bg-brand-50 text-brand-700; }
            .badge-green   { @apply bg-emerald-50 text-emerald-700; }
            .badge-red     { @apply bg-red-50 text-red-700; }

            /* Avatar inisial */
            .avatar        { @apply inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600; }

            /* Empty state */
            .empty-state   { @apply flex flex-col items-center justify-center px-4 py-12 text-center; }
            .empty-state > .material-symbols-outlined { @apply mb-2 text-[32px] text-slate-300; }
            .empty-title   { @apply text-sm font-medium text-slate-700; }
            .empty-text    { @apply mt-1 max-w-sm text-xs text-slate-500; }

            /* Alert */
            .alert         { @apply flex items-start gap-2.5 rounded-md border px-3.5 py-2.5 text-[13px]; }
            .alert > .material-symbols-outlined { @apply mt-px text-[18px]; }
            .alert-success { @apply border-emerald-200 bg-emerald-50 text-emerald-800; }
            .alert-error   { @apply border-red-200 bg-red-50 text-red-800; }
            .alert-warning { @apply border-amber-200 bg-amber-50 text-amber-800; }
            .alert-info    { @apply border-blue-200 bg-blue-50 text-blue-800; }

            /* Modal */
            .modal-backdrop { @apply fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4; }
            .modal-panel   { @apply w-full max-w-md max-h-[90vh] overflow-y-auto rounded-lg bg-white shadow-xl; }
            .modal-header  { @apply flex items-center justify-between gap-3 px-5 py-3.5 border-b border-slate-200; }
            .modal-title   { @apply text-sm font-semibold text-slate-900; }
            .modal-body    { @apply p-5 space-y-4; }
            .modal-footer  { @apply flex items-center justify-end gap-2 px-5 py-3 border-t border-slate-200 bg-slate-50; }

            /* Daftar detail (label : nilai) */
            .dl-grid       { @apply grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2; }
            .dl-label      { @apply text-xs text-slate-500; }
            .dl-value      { @apply mt-0.5 text-[13.5px] font-medium text-slate-900; }

            /* Navigasi sidebar */
            .nav-group     { @apply px-3 pt-5 pb-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-500; }
            .nav-item      { @apply relative flex items-center justify-between gap-2 rounded-md px-3 h-9 text-[13px] text-slate-300 hover:bg-white/5 hover:text-white transition-colors; }
            .nav-item .material-symbols-outlined { @apply text-[19px] text-slate-400; }
            .nav-item.is-active { @apply bg-white/10 text-white font-medium; }
            .nav-item.is-active::before { content: ''; @apply absolute left-0 top-2 bottom-2 w-[3px] rounded-r bg-brand-500; }
            .nav-item.is-active .material-symbols-outlined { @apply text-brand-400; }
            .nav-badge     { @apply rounded px-1.5 py-px text-[11px] font-semibold tabular-nums; }
        }
    </style>
    @stack('styles')
</head>
<body>
    @php
        $navActive = fn (bool $active) => $active ? 'nav-item is-active' : 'nav-item';
    @endphp

    <!-- Overlay sidebar (mobile) -->
    <div id="sidebarOverlay" class="fixed inset-0 z-40 hidden bg-slate-900/50 lg:hidden" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 flex w-sidebar-width -translate-x-full flex-col bg-navy-900 transition-transform duration-200 lg:translate-x-0">
        <div class="flex h-header-height shrink-0 items-center gap-2.5 border-b border-white/10 px-4">
            <img src="{{ asset('images/Logo_City_Courier.png') }}" alt="City Courier" class="h-8 w-auto max-w-[44px] object-contain shrink-0">
            <div class="min-w-0 leading-tight">
                <div class="text-sm font-semibold text-white">CityCourier</div>
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

            <div class="flex items-center gap-2 sm:gap-3">
                <a href="{{ route('download.app') }}" class="btn btn-secondary btn-sm hidden sm:inline-flex">
                    <span class="material-symbols-outlined">download</span>
                    Download App
                </a>
                <div class="hidden h-6 w-px bg-slate-200 sm:block"></div>
                <div class="flex items-center gap-2.5">
                    <div class="avatar bg-navy-900 text-white">
                        {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
                    </div>
                    <div class="hidden flex-col leading-tight md:flex">
                        <span class="text-[13px] font-medium text-slate-800">{{ auth()->user()->name ?? 'Admin City Courier' }}</span>
                        <span class="text-[11.5px] text-slate-500">{{ auth()->user()?->roles?->first()?->name ? ucwords(str_replace('-', ' ', auth()->user()->roles->first()->name)) : 'Administrator' }}</span>
                    </div>
                </div>
                <form action="{{ route('logout') }}" method="POST" class="m-0">
                    @csrf
                    <button type="submit" class="btn-icon btn-icon-danger" title="Keluar">
                        <span class="material-symbols-outlined">logout</span>
                    </button>
                </form>
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

    document.addEventListener('DOMContentLoaded', function () {
        const hamburger = document.getElementById('hamburgerBtn');
        if (hamburger) hamburger.addEventListener('click', toggleSidebar);

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
