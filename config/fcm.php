<?php

return [
    'enabled' => filter_var(env('FCM_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'project_id' => env('FCM_PROJECT_ID'),
    'credentials_path' => env('FCM_CREDENTIALS_PATH', env('FIREBASE_CREDENTIALS', storage_path('app/firebase_credentials.json'))),
    'static_access_token' => env('FCM_STATIC_ACCESS_TOKEN'),
    'android_channel_id' => env('FCM_ANDROID_CHANNEL_ID', 'city_courier_chat'),
    'timeout_seconds' => (int) env('FCM_HTTP_TIMEOUT', 10),
];
