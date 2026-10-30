<?php

/**
 * iPaymu Integration Configuration.
 *
 * Prinsip keamanan:
 * - API Key, VA (Merchant ID), dan secret HANYA dibaca dari environment
 *   variable (.env). Jangan pernah menyimpan kredensial di kode/frontend.
 * - Payout/disbursement iPaymu membutuhkan AKTIVASI layanan dari merchant
 *   ( approval relasi iPaymu). Selama aktivasi belum ada, fitur penarikan
 *   tetap ditolak dengan pesan jelas — TIDAK ada simulasi sukses.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Base URL API iPaymu
    |--------------------------------------------------------------------------
    | Sandbox: https://sandbox.ipaymu.com
    | Production: https://api.ipaymu.com
    */
    'base_url' => env('IPAYMU_BASE_URL', 'https://sandbox.ipaymu.com'),

    /*
    |--------------------------------------------------------------------------
    | Kredensial Merchant iPaymu
    |--------------------------------------------------------------------------
    | va         : Virtual Account / Merchant ID dari dashboard iPaymu.
    | api_key    : API Key dari dashboard iPaymu.
    | secret_key : Secret key (opsional, untuk beberapa endpoint).
    */
    'merchant_id' => env('IPAYMU_MERCHANT_ID'),
    'va'          => env('IPAYMU_VA'),
    'api_key'     => env('IPAYMU_API_KEY'),
    'secret_key'  => env('IPAYMU_SECRET_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Status Aktivasi Payout / Disbursement
    |--------------------------------------------------------------------------
    | iPaymu menonaktifkan endpoint disbursement secara default dan mengaktifkannya
    | setelah verifikasi merchant. Set IPAYMU_PAYOUT_ENABLED=true HANYA setelah
    | iPaymu mengonfirmasi layanan payout sudah aktif untuk City Courier.
    */
    'payout_enabled' => (bool) env('IPAYMU_PAYOUT_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Path Endpoint Disbursement & Inquiry
    |--------------------------------------------------------------------------
    | Dibuat configurable supaya penyesuaian versi API iPaymu tidak
    | memaksa perubahan kode. Default mengikuti REST API v2 iPaymu yang
    | memakai prefix /api (mis. https://sandbox.ipaymu.com/api/v2/payment):
    | disbursement POST {base}/api/v2/disbursement, inquiry POST
    | {base}/api/v2/transaction dengan body {"trx_id": "..."}.
    */
    'disburse_path' => env('IPAYMU_DISBURSE_PATH', '/api/v2/disbursement'),
    'inquiry_path'  => env('IPAYMU_INQUIRY_PATH', '/api/v2/transaction'),

    /*
    |--------------------------------------------------------------------------
    | Path Endpoint Pembayaran Masuk (Checkout VA & QRIS)
    |--------------------------------------------------------------------------
    | REST API v2 resmi (docs.ipaymu.com → Payment → Direct):
    | - POST {base}/api/v2/payment/direct      (buat VA / QRIS)
    | - GET  {base}/api/v2/payment-channels    (daftar saluran aktif)
    | notifyUrl dikirim pada body create → route /api/ipaymu/callback
    | (callback publik yang sama dengan webhook penarikan; domain & path ini
    | SUDAH terdaftar di dashboard iPaymu).
    */
    'payment_direct_path'   => env('IPAYMU_PAYMENT_DIRECT_PATH', '/api/v2/payment/direct'),
    'payment_channels_path' => env('IPAYMU_PAYMENT_CHANNELS_PATH', '/api/v2/payment-channels'),

    /*
    |--------------------------------------------------------------------------
    | Kadaluarsa Pembayaran Masuk
    |--------------------------------------------------------------------------
    | VA   : default 24 jam (default iPaymu).
    | QRIS : 60 menit (checkout on-demand — QR harus segera dibayar).
    */
    'payment_expired_hours'  => (int) env('IPAYMU_PAYMENT_EXPIRED_HOURS', 24),
    'qris_expired_minutes'   => (int) env('IPAYMU_QRIS_EXPIRED_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Saluran QRIS
    |--------------------------------------------------------------------------
    | Di iPaymu v2, QRIS memakai paymentChannel "mpm" (Merchant Presented Mode).
    */
    'qris_channel' => env('IPAYMU_QRIS_CHANNEL', 'mpm'),

    /*
    |--------------------------------------------------------------------------
    | Path Endpoint Inquiry Nama Akuntujuan (DANA Name Inquiry)
    |--------------------------------------------------------------------------
    | Endpoint penyedia payout biasanya menyediakan "account name inquiry":
    | cek nama pemilik nomor e-wallet tujuan SEBELUM uang dikirim. Endpoint
    | ini BELUM ada di dokumentasi publik iPaymu v2 → default KOSONG dan
    | fitur preview nama di aplikasi otomatis menampilkan "belum tersedia".
    | Begitu iPaymu mengaktifkan layanan payout dan memberikan spesifiknya,
    | isi PATH ini (mis. /api/v2/disbursement/inquiry-account) tanpa mengubah
    | kode — UI Flutter langsung menyala. Payload dikirim (dinormalisasi
    | ke bentuk 628…): {"account": "62812xxxx"}; respons dibaca fleksibel.
    */
    'inquiry_account_path' => env('IPAYMU_INQUIRY_ACCOUNT_PATH', ''),

    /*
    |--------------------------------------------------------------------------
    | Webhook
    |--------------------------------------------------------------------------
    | Secret untuk memvalidasi callback dari iPaymu. Jika belum di-set,
    | controller webhook wajib memverifikasi status via inquiry resmi
    | iPaymu sebelum mengubah status penarikan.
    */
    'webhook_secret' => env('IPAYMU_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('IPAYMU_TIMEOUT', 30),
];
