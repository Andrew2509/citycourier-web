<?php

/**
 * Admin Menu Configuration
 * 
 * Single source of truth for sidebar navigation AND role permission matrix.
 * Every menu item here maps directly to a sidebar entry and a permission group
 * in the Edit Role / Create Role pages.
 * 
 * Structure:
 * - id: unique identifier (used as permission prefix, e.g., "dashboard.view")
 * - name: display name (must match sidebar label exactly)
 * - icon: Material Symbols icon name
 * - section: sidebar section grouping
 * - actions: available permission actions (view is always included)
 * - route: route name for the menu link (null = no direct link)
 */

return [

    'sections' => [
        'menu_utama' => 'Menu Utama',
        'manajemen' => 'Manajemen',
        'ci_work' => 'City-Work Operasional',
        'keuangan' => 'Keuangan & Wallet',
        'penelitian' => 'Penelitian',
        'sistem_keamanan' => 'Sistem & Keamanan',
        'unduh_aplikasi' => 'Unduh Aplikasi',
    ],

    'menus' => [

        // ─── Menu Utama ───────────────────────────────────────
        [
            'id' => 'dashboard',
            'name' => 'Dashboard',
            'icon' => 'dashboard',
            'section' => 'menu_utama',
            'actions' => ['view'],
            'route' => 'admin.dashboard',
            'description' => 'Akses ke halaman dashboard utama',
        ],

        // ─── Manajemen ────────────────────────────────────────
        [
            'id' => 'couriers',
            'name' => 'Kurir',
            'icon' => 'two_wheeler',
            'section' => 'manajemen',
            'actions' => ['view', 'create', 'edit', 'delete'],
            'route' => 'admin.couriers',
            'description' => 'Kelola data kurir, verifikasi, dan profil',
        ],
        [
            'id' => 'orders',
            'name' => 'Pesanan',
            'icon' => 'inventory_2',
            'section' => 'manajemen',
            'actions' => ['view', 'create', 'edit', 'delete'],
            'route' => 'admin.orders',
            'description' => 'Kelola data pesanan dan status pengiriman',
        ],
        [
            'id' => 'shipments',
            'name' => 'Pengiriman',
            'icon' => 'local_shipping',
            'section' => 'manajemen',
            'actions' => ['view', 'create', 'edit', 'delete'],
            'route' => 'admin.shipments.index',
            'description' => 'Kelola data pengiriman dan pelacakan',
        ],
        [
            'id' => 'drop_points',
            'name' => 'Drop Point',
            'icon' => 'warehouse',
            'section' => 'manajemen',
            'actions' => ['view', 'create', 'edit', 'delete'],
            'route' => 'admin.drop-points.index',
            'description' => 'Kelola lokasi drop point',
        ],

        // ─── City-Work Operasional ────────────────────────────
        [
            'id' => 'ci_work',
            'name' => 'Dashboard Kerja',
            'icon' => 'monitoring',
            'section' => 'ci_work',
            'actions' => ['view'],
            'route' => 'admin.ci-work.index',
            'description' => 'Dashboard monitoring kerja kurir',
        ],
        [
            'id' => 'ci_work_attendance',
            'name' => 'Presensi Kurir',
            'icon' => 'badge',
            'section' => 'ci_work',
            'actions' => ['view', 'edit'],
            'route' => 'admin.ci-work.attendance',
            'description' => 'Kelola presensi dan kehadiran kurir',
        ],
        [
            'id' => 'ci_work_tasks',
            'name' => 'Manajemen Tugas',
            'icon' => 'assignment',
            'section' => 'ci_work',
            'actions' => ['view', 'create', 'edit', 'delete'],
            'route' => 'admin.ci-work.tasks',
            'description' => 'Kelola tugas dan penugasan kurir',
        ],
        [
            'id' => 'ci_work_finance',
            'name' => 'Keuangan & Setoran',
            'icon' => 'payments',
            'section' => 'ci_work',
            'actions' => ['view', 'edit'],
            'route' => 'admin.ci-work.finance',
            'description' => 'Kelola keuangan dan setoran kurir',
        ],
        [
            'id' => 'ci_work_dana_transactions',
            'name' => 'Transaksi DANA',
            'icon' => 'dana',
            'section' => 'ci_work',
            'actions' => ['view'],
            'route' => 'admin.ci-work.dana-transactions',
            'description' => 'Daftar dan verifikasi penarikan saldo ke DANA',
        ],

        // ─── Penelitian ────────────────────────────────────────
        [
            'id' => 'research_study',
            'name' => 'Studi Pendahuluan',
            'icon' => 'science',
            'section' => 'penelitian',
            'actions' => ['view', 'export', 'delete'],
            'route' => 'admin.research.index',
            'description' => 'Lihat, analisis, ekspor, dan hapus jawaban Studi Pendahuluan',
        ],

        // ─── Sistem & Keamanan ────────────────────────────────
        [
            'id' => 'users',
            'name' => 'Manajemen User',
            'icon' => 'group',
            'section' => 'sistem_keamanan',
            'actions' => ['view', 'create', 'edit', 'delete'],
            'route' => 'admin.users.index',
            'description' => 'Kelola akun pengguna admin',
        ],
        [
            'id' => 'roles',
            'name' => 'Manajemen Role',
            'icon' => 'admin_panel_settings',
            'section' => 'sistem_keamanan',
            'actions' => ['view', 'create', 'edit', 'delete'],
            'route' => 'admin.roles.index',
            'description' => 'Kelola role dan hak akses',
        ],
        [
            'id' => 'permissions',
            'name' => 'Manajemen Permission',
            'icon' => 'lock_person',
            'section' => 'sistem_keamanan',
            'actions' => ['view', 'create', 'edit', 'delete'],
            'route' => 'admin.permissions.index',
            'description' => 'Kelola permission sistem',
        ],
        [
            'id' => 'settings',
            'name' => 'Provider & Integrasi',
            'icon' => 'hub',
            'section' => 'sistem_keamanan',
            'actions' => ['view', 'edit'],
            'route' => 'admin.settings.providers',
            'description' => 'Kelola provider dan integrasi eksternal',
        ],
        [
            'id' => 'documentation',
            'name' => 'Dokumentasi API',
            'icon' => 'code',
            'section' => 'sistem_keamanan',
            'actions' => ['view'],
            'route' => 'api.documentation',
            'description' => 'Akses dokumentasi API',
        ],

        // ─── Unduh Aplikasi ───────────────────────────────────
        [
            'id' => 'app_download',
            'name' => 'Kelola APK',
            'icon' => 'install_mobile',
            'section' => 'unduh_aplikasi',
            'actions' => ['view', 'create', 'edit', 'delete'],
            'route' => 'admin.app-download',
            'description' => 'Kelola file APK aplikasi mobile',
        ],

        // ─── Keuangan & Wallet ────────────────────────────────
        [
            'id' => 'finance_dashboard',
            'name' => 'Payment & Wallet',
            'icon' => 'account_balance_wallet',
            'section' => 'keuangan',
            'actions' => ['view', 'edit'],
            'route' => 'admin.finance.index',
            'description' => 'Dashboard monitoring sistem payment & wallet',
        ],
        [
            'id' => 'finance_wallets',
            'name' => 'Dompet & Rekonsiliasi',
            'icon' => 'balance',
            'section' => 'keuangan',
            'actions' => ['view', 'edit'],
            'route' => 'admin.finance.wallets',
            'description' => 'Kelola saldo, freeze wallet, dan rekonsiliasi ledger',
        ],
        [
            'id' => 'finance_payouts',
            'name' => 'Top-Up, Payout & Refund',
            'icon' => 'currency_exchange',
            'section' => 'keuangan',
            'actions' => ['view', 'edit'],
            'route' => 'admin.finance.payouts',
            'description' => 'Monitor top-up, pembayaran, earnings kurir, auto-payout, dan refund',
        ],
        [
            'id' => 'finance_reports',
            'name' => 'Laporan & Audit Keuangan',
            'icon' => 'summarize',
            'section' => 'keuangan',
            'actions' => ['view'],
            'route' => 'admin.finance.reports',
            'description' => 'Laporan keuangan, ekspor CSV, dan audit log',
        ],
        [
            'id' => 'finance_settings',
            'name' => 'Gateway & Komisi',
            'icon' => 'tune',
            'section' => 'keuangan',
            'actions' => ['view', 'edit'],
            'route' => 'admin.finance.settings',
            'description' => 'Konfigurasi mode demo/sandbox/production, komisi, dan min payout',
        ],
    ],

    /**
     * Permission action labels (Indonesian)
     */
    'action_labels' => [
        'view' => 'Lihat',
        'create' => 'Tambah',
        'edit' => 'Edit',
        'delete' => 'Hapus',
    ],

    /**
     * Permission action descriptions
     */
    'action_descriptions' => [
        'view' => 'Melihat data',
        'create' => 'Menambah data baru',
        'edit' => 'Mengubah data',
        'delete' => 'Menghapus data',
    ],
];
