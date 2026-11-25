<?php

return [
    'enabled' => filter_var(env('VOIP_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    'ring_timeout_seconds' => max(15, min(120, (int) env('VOIP_RING_TIMEOUT_SECONDS', 45))),
    'max_call_seconds' => max(300, (int) env('VOIP_MAX_CALL_SECONDS', 14400)),
    'stun_urls' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('VOIP_STUN_URLS', 'stun:stun.l.google.com:19302,stun:stun1.l.google.com:19302'))
    ))),
    'turn_urls' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('VOIP_TURN_URLS', ''))
    ))),
    'turn_shared_secret' => env('VOIP_TURN_SHARED_SECRET'),
    'turn_username' => env('VOIP_TURN_USERNAME'),
    'turn_credential' => env('VOIP_TURN_CREDENTIAL'),
    'turn_ttl_seconds' => max(60, min(86400, (int) env('VOIP_TURN_TTL_SECONDS', 3600))),
    'fcm_call_channel_id' => env('FCM_ANDROID_CALL_CHANNEL_ID', 'city_courier_calls'),
];
