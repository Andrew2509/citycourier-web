<?php

/**
 * Withdrawal Configuration.
 *
 * PRD §15: Fee from configuration, not hardcoded.
 * PRD §16: MIN_WITHDRAWAL_AMOUNT configurable.
 * PRD §47: Fraud prevention - limits configurable.
 * PRD §48: Rate limit configurable.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Minimum Withdrawal Amount (PRD §16)
    |--------------------------------------------------------------------------
    | Production value should follow CityCourier policy and DANA limits.
    */
    'minimum' => (int) env('WITHDRAWAL_MINIMUM', 10000),

    /*
    |--------------------------------------------------------------------------
    | Maximum Withdrawal Amount (PRD §47)
    |--------------------------------------------------------------------------
    */
    'maximum' => (int) env('WITHDRAWAL_MAXIMUM', 5000000),

    /*
    |--------------------------------------------------------------------------
    | Admin Fee (PRD §15)
    |--------------------------------------------------------------------------
    | Must follow DANA agreement and CityCourier configuration.
    | Do not hardcode in UI.
    */
    'admin_fee' => (int) env('WITHDRAWAL_ADMIN_FEE', 2500),

    /*
    |--------------------------------------------------------------------------
    | Daily Withdrawal Limit (PRD §47)
    |--------------------------------------------------------------------------
    */
    'daily_limit' => (int) env('WITHDRAWAL_DAILY_LIMIT', 5000000),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting (PRD §48)
    |--------------------------------------------------------------------------
    | 5 requests per 10 minutes per courier.
    */
    'rate_limit_requests' => (int) env('WITHDRAWAL_RATE_LIMIT_REQUESTS', 5),
    'rate_limit_window_minutes' => (int) env('WITHDRAWAL_RATE_LIMIT_WINDOW', 10),

    /*
    |--------------------------------------------------------------------------
    | Withdrawal Statuses (PRD §60)
    |--------------------------------------------------------------------------
    */
    'statuses' => [
        'pending'    => 'pending',
        'reserved'   => 'reserved',
        'processing' => 'processing',
        'success'    => 'success',
        'failed'     => 'failed',
        'reversed'   => 'reversed',
        'cancelled'  => 'cancelled',
    ],
];
