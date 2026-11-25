<?php

return [
    'enabled' => filter_var(env('WHATSAPP_CLOUD_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
    'base_url' => rtrim((string) env('WHATSAPP_BASE_URL', 'https://graph.facebook.com'), '/'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'waba_id' => env('WHATSAPP_WABA_ID'),
    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
    'app_secret' => env('WHATSAPP_APP_SECRET'),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    'default_country_code' => env('WHATSAPP_DEFAULT_COUNTRY_CODE', '62'),
    'customer_service_window_hours' => (int) env('WHATSAPP_WINDOW_HOURS', 24),
    'utility_template_name' => env('WHATSAPP_UTILITY_TEMPLATE_NAME', 'city_courier_chat_notification'),
    'utility_template_language' => env('WHATSAPP_UTILITY_TEMPLATE_LANG', 'id'),
    'opt_out_keywords' => ['STOP', 'UNSUBSCRIBE', 'BERHENTI'],
    'timeout_seconds' => (int) env('WHATSAPP_HTTP_TIMEOUT', 15),
];
