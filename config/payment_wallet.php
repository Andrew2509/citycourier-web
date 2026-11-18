<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment & Wallet Environment Mode
    |--------------------------------------------------------------------------
    |
    | Supported modes: "demo", "sandbox", "production".
    | In "demo" and "sandbox" modes, test/simulated flows are allowed.
    | In "production" mode, MockPaymentProvider is strictly disabled and
    | customer withdrawals stay blocked unless regulatory approval is enabled.
    |
    */
    'mode' => env('PAYMENT_WALLET_MODE', 'sandbox'),

    'active_payment_provider' => env('PAYMENT_ACTIVE_PROVIDER', 'xendit'),
    'active_payout_provider' => env('PAYOUT_ACTIVE_PROVIDER', 'xendit'),

    /*
    |--------------------------------------------------------------------------
    | Regulatory & Feature Gates
    |--------------------------------------------------------------------------
    |
    | Customer withdrawal must remain disabled in production until the
    | regulatory and payment provider structure approves stored-value payouts.
    |
    */
    'customer_withdrawal_regulatory_approved' => (bool) env('CUSTOMER_WITHDRAWAL_REGULATORY_APPROVED', false),
    'customer_withdrawal_enabled_in_sandbox' => (bool) env('CUSTOMER_WITHDRAWAL_SANDBOX_ENABLED', true),
    'customer_withdrawal_min_amount_idr' => (int) env('CUSTOMER_WITHDRAWAL_MIN_IDR', 10000),
    'customer_withdrawal_max_amount_idr' => (int) env('CUSTOMER_WITHDRAWAL_MAX_IDR', 5000000),
    'customer_withdrawal_fee_idr' => (int) env('CUSTOMER_WITHDRAWAL_FEE_IDR', 0),

    /*
    |--------------------------------------------------------------------------
    | Top-Up Configuration
    |--------------------------------------------------------------------------
    */
    'topup' => [
        'min_amount_idr' => (int) env('WALLET_TOPUP_MIN_IDR', 10000),
        'max_amount_idr' => (int) env('WALLET_TOPUP_MAX_IDR', 10000000),
        'expiry_minutes' => (int) env('WALLET_TOPUP_EXPIRY_MINUTES', 60),
        'allowed_channels' => [
            'QRIS',
            'BCA_VIRTUAL_ACCOUNT',
            'BNI_VIRTUAL_ACCOUNT',
            'BRI_VIRTUAL_ACCOUNT',
            'MANDIRI_VIRTUAL_ACCOUNT',
            'PERMATA_VIRTUAL_ACCOUNT',
            'ID_DANA',
            'ID_OVO',
            'ID_SHOPEEPAY',
            'ID_LINKAJA',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Order Payment & Refund Rules
    |--------------------------------------------------------------------------
    */
    'order_payment' => [
        'allow_partial_wallet' => false,
    ],

    'refund' => [
        'auto_approve_max_idr' => (int) env('WALLET_REFUND_AUTO_APPROVE_MAX_IDR', 0),
        'eligible_statuses' => ['pending', 'cancelled', 'failed', 'expired'],
        'refund_window_hours' => (int) env('WALLET_REFUND_WINDOW_HOURS', 168),
    ],

    /*
    |--------------------------------------------------------------------------
    | Courier Earnings & Automatic Payout Rules
    |--------------------------------------------------------------------------
    */
    'courier_earnings' => [
        'default_commission_rate_percent' => (float) env('COURIER_COMMISSION_RATE_PERCENT', 10.0),
        'settlement_waiting_minutes' => (int) env('COURIER_SETTLEMENT_WAITING_MINUTES', 1440),
        'auto_release_enabled' => (bool) env('COURIER_AUTO_RELEASE_ENABLED', true),
    ],

    'courier_payout' => [
        'auto_enabled' => (bool) env('COURIER_AUTO_PAYOUT_ENABLED', true),
        'min_amount_idr' => (int) env('COURIER_MIN_PAYOUT_IDR', 20000),
        'max_amount_idr' => (int) env('COURIER_MAX_PAYOUT_IDR', 25000000),
        'fee_idr' => (int) env('COURIER_PAYOUT_FEE_IDR', 0),
        'schedule_cron' => env('COURIER_AUTO_PAYOUT_CRON', '0 * * * *'),
        'require_verified_destination' => (bool) env('COURIER_REQUIRE_VERIFIED_DESTINATION', true),
        'max_retry_attempts' => (int) env('COURIER_PAYOUT_MAX_RETRIES', 3),
        'timeout_recheck_minutes' => (int) env('COURIER_PAYOUT_TIMEOUT_RECHECK_MINUTES', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Xendit Provider Configuration (Primary Candidate)
    |--------------------------------------------------------------------------
    */
    'xendit' => [
        'base_url' => env('XENDIT_BASE_URL', 'https://api.xendit.co'),
        'secret_key' => env('XENDIT_SECRET_KEY', ''),
        'public_key' => env('XENDIT_PUBLIC_KEY', ''),
        'callback_token' => env('XENDIT_WEBHOOK_CALLBACK_TOKEN', ''),
        'timeout_seconds' => (int) env('XENDIT_TIMEOUT_SECONDS', 30),
        'api_version' => env('XENDIT_API_VERSION', '2024-11-11'),
    ],

    /*
    |--------------------------------------------------------------------------
    | DOKU Provider Configuration (Alternative Candidate)
    |--------------------------------------------------------------------------
    */
    'doku' => [
        'sandbox_url' => env('DOKU_SANDBOX_URL', 'https://api-sandbox.doku.com'),
        'production_url' => env('DOKU_PRODUCTION_URL', 'https://api.doku.com'),
        'client_id' => env('DOKU_CLIENT_ID', ''),
        'secret_key' => env('DOKU_SECRET_KEY', ''),
        'private_key' => env('DOKU_PRIVATE_KEY', ''),
        'kirim_inquiry_enabled' => (bool) env('DOKU_KIRIM_INQUIRY_ENABLED', true),
        'kirim_channel_id' => env('DOKU_KIRIM_CHANNEL_ID', 'H2H'),
        'kirim_customer_number' => env('DOKU_KIRIM_CUSTOMER_NUMBER', '6281234567890'),
        'kirim_token_path' => env('DOKU_KIRIM_TOKEN_PATH', '/authorization/v1/access-token/b2b'),
        'kirim_inquiry_path' => env('DOKU_KIRIM_INQUIRY_PATH', '/snap/v1.1/emoney/bank-account-inquiry'),
        'timeout_seconds' => (int) env('DOKU_TIMEOUT_SECONDS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ledger Chart of Accounts
    |--------------------------------------------------------------------------
    */
    'ledger_accounts' => [
        'WALLET_AVAILABLE' => 'Wallet Available Balance Liability',
        'WALLET_PENDING' => 'Wallet Pending Earnings Liability',
        'WALLET_RESERVED' => 'Wallet Reserved Payout/Withdrawal Liability',
        'SYSTEM_GATEWAY_CLEARING' => 'Platform External Gateway Clearing',
        'SYSTEM_ORDER_ESCROW' => 'Order Payment Escrow Clearing',
        'SYSTEM_PLATFORM_REVENUE' => 'Platform Commission & Fee Revenue',
        'SYSTEM_PAYOUT_CLEARING' => 'Platform External Payout Clearing',
        'SYSTEM_REFUND_CLEARING' => 'Platform Refund Settlement Clearing',
        'SYSTEM_SUSPENSE_ADJUSTMENT' => 'Authorized Reconciliation Suspense Adjustment',
    ],

];
